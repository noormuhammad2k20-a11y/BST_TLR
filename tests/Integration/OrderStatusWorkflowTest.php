<?php

namespace Tests\Integration;

use App\Models\{Customer, Notification, Order, OrderStatusHistory, SmsLog, User};
use App\Services\{CustomerNotificationDispatcher, OrderService, Settings};
use Illuminate\Support\Facades\{Cache, DB, Http};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class OrderStatusWorkflowTest extends TestCase
{
    private Customer $customer;
    private User $staff;
    private array $settings = [];
    private bool $committed = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL') !== '1') $this->markTestSkipped('Requires isolated atelier_integrity_test MySQL.');
        $this->assertSame('atelier_integrity_test', DB::connection()->getDatabaseName());
        Settings::flush();
        $this->settings = Settings::all();
        DB::beginTransaction();
        Cache::flush();
        Settings::put(['sms_enabled' => false, 'auto_status_enabled' => true, 'auto_status_unit' => 'minutes',
            'auto_status_received_delay' => 5, 'auto_status_pending_hours' => 10, 'auto_status_progress_delay' => 30,
            'auto_status_verify_delay' => 1, 'auto_status_ready_delay' => 1, 'auto_delivery_update' => true]);
        $this->staff = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->staff);
        $this->customer = Customer::create(['name' => 'Workflow test', 'phone' => '03001234567']);
    }

    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL') === '1') {
            while (DB::transactionLevel() > 0) DB::rollBack();
            if ($this->committed) {
                DB::table('orders')->where('customer_id', $this->customer->id)->delete();
                DB::table('customers')->where('id', $this->customer->id)->delete();
                DB::table('users')->where('id', $this->staff->id)->delete();
                Settings::put($this->settings);
            }
            Settings::flush();
            $this->travelBack();
        }
        parent::tearDown();
    }

    private function order(string $status = 'Received'): Order
    {
        return Order::create(['customer_id' => $this->customer->id, 'status' => $status,
            'total' => 100, 'advance' => 0, 'balance' => 100, 'delivery_date' => today(),
            'items' => [['name' => 'Shirt', 'qty' => 1, 'price' => 100]]]);
    }

    public function test_creation_automation_manual_stages_and_persisted_history(): void
    {
        $this->travelTo(now()->startOfMinute());
        $service = app(OrderService::class);
        $order = $service->create(['customer_id' => $this->customer->id, 'garment' => 'Shirt',
            'total' => 100, 'advance' => 0, 'delivery_date' => today()->toDateString(), 'status' => 'Delivered']);
        $this->assertSame('Received', $order->fresh()->status);
        $this->assertFalse($order->is_overdue);
        $this->assertFalse(Order::overdue()->whereKey($order->id)->exists());
        foreach ([5 => 'Pending', 10 => 'Stitching', 30 => 'Ready for Verification'] as $minutes => $expected) {
            $this->travel($minutes)->minutes();
            $this->get(route('orders.index'))->assertOk();
            $this->assertSame($expected, $order->fresh()->status);
        }
        $this->assertSame(1, Notification::where('order_id', $order->id)->where('title', 'Garments Ready for Verification')->count());
        $this->travel(2)->days();
        $service->reconcileElapsedOrders();
        $this->assertSame('Ready for Verification', $order->fresh()->status);
        $this->assertTrue($order->fresh()->is_overdue);
        $service->changeStatus($order, 'Ready');
        $this->travel(2)->days();
        $service->reconcileElapsedOrders();
        $service->recalculateBalance($order->fresh());
        $this->assertSame('Ready', $order->fresh()->status);
        $service->changeStatus($order, 'Ready');
        $service->changeStatus($order, 'Delivered');
        $this->assertSame(Order::WORKFLOW, $order->statusHistories()->pluck('to_status')->all());
        $this->assertFalse($order->fresh()->is_overdue);
        $this->assertSame('Delivered', $order->delivery()->first()->status);
        $this->getJson(route('live.orders'))->assertOk();
        $this->get(route('orders.index'))->assertOk();
        $this->expectException(ValidationException::class);
        $service->changeStatus($order, 'Ready');
    }

    public function test_update_cannot_skip_verification_and_same_status_does_not_add_history(): void
    {
        $order = $this->order();
        $service = app(OrderService::class);
        $service->update($order, ['status' => 'Received']);
        $this->assertSame(0, $order->statusHistories()->count());
        $this->expectException(ValidationException::class);
        $service->update($order, ['status' => 'Ready']);
    }

    public function test_missing_and_future_dates_are_not_overdue_but_yesterday_is(): void
    {
        foreach ([null => false, today()->toDateString() => false, today()->addDay()->toDateString() => false,
            today()->subDay()->toDateString() => true] as $date => $expected) {
            $order = $this->order();
            $order->update(['delivery_date' => $date ?: null]);
            $this->assertSame($expected, $order->fresh()->is_overdue);
            $this->assertSame($expected, Order::overdue()->whereKey($order->id)->exists());
        }
    }

    public function test_pickup_sms_only_after_ready_and_once_across_both_write_paths(): void
    {
        $order = $this->order('Ready for Verification');
        $second = $this->order('Ready for Verification');
        $this->committed = true;
        DB::commit();
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'veevo', 'veevo_api_key' => 'test-only']);
        Http::fake(['api.veevotech.com/*' => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'test-1'])]);
        $this->assertFalse(CustomerNotificationDispatcher::dispatch('order-ready', $order)['sent']);
        Http::assertNothingSent();
        $service = app(OrderService::class);
        $service->changeStatus($order, 'Ready');
        $service->changeStatus($order, 'Ready');
        $service->update($order, ['status' => 'Ready']);
        CustomerNotificationDispatcher::dispatch('order-ready', $order);
        Http::assertSentCount(1);
        $service->update($second, ['status' => 'Ready']);
        Http::assertSentCount(2);
        $this->assertNotNull($order->fresh()->notified_at);
        $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_sms_failure_does_not_rollback_ready_or_repeat_attempt(): void
    {
        $order = $this->order('Ready for Verification');
        $this->committed = true;
        DB::commit();
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'veevo', 'veevo_api_key' => 'test-only']);
        Http::fake(['api.veevotech.com/*' => Http::response(['STATUS' => 'FAILED'], 503)]);
        app(OrderService::class)->changeStatus($order, 'Ready');
        app(OrderService::class)->changeStatus($order, 'Ready');
        CustomerNotificationDispatcher::dispatch('order-ready', $order);
        Http::assertSentCount(1);
        $this->assertSame('Ready', $order->fresh()->status);
        $this->assertNull($order->fresh()->notified_at);
        $this->assertNotNull($order->fresh()->ready_sms_attempted_at);
    }

    public function test_zero_delay_disables_stage_and_edits_do_not_reset_stage_clock(): void
    {
        $order = $this->order();
        OrderStatusHistory::create(['order_id' => $order->id, 'to_status' => 'Received', 'created_at' => now()->subMinutes(6)]);
        Settings::put(['auto_status_received_delay' => 0]);
        app(OrderService::class)->reconcileElapsedOrders();
        $this->assertSame('Received', $order->fresh()->status);
        Settings::put(['auto_status_received_delay' => 5]);
        $order->update(['notes' => 'Routine edit']);
        app(OrderService::class)->reconcileElapsedOrders();
        $this->assertSame('Pending', $order->fresh()->status);
    }

    public function test_paid_order_stays_ready_and_missing_sms_configuration_is_safe(): void
    {
        $order = $this->order('Ready for Verification');
        $order->update(['total' => 0, 'balance' => 0]);
        $this->committed = true;
        DB::commit();
        Settings::put(['sms_enabled' => true, 'veevo_api_key' => '', 'sms_provider' => 'veevo']);
        Http::fake();
        app(OrderService::class)->changeStatus($order, 'Ready');
        app(OrderService::class)->recalculateBalance($order->fresh());
        Http::assertNothingSent();
        $this->assertSame('Ready', $order->fresh()->status);
        $this->assertNull($order->fresh()->notified_at);
        $this->assertNotNull($order->fresh()->ready_sms_attempted_at);
    }

    public function test_page_visit_recovers_a_ready_transition_without_repeating_sms(): void
    {
        $order = $this->order('Ready');
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'Ready for Verification', 'to_status' => 'Ready']);
        $this->committed = true;
        DB::commit();
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'veevo', 'veevo_api_key' => 'test-only']);
        Http::fake(['api.veevotech.com/*' => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'recover-1'])]);
        $this->get(route('orders.index'))->assertOk();
        $this->get(route('orders.index'))->assertOk();
        $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame('Ready', $order->fresh()->status);
    }

    public function test_one_visit_after_long_absence_catches_up_at_original_deadlines(): void
    {
        $this->travelTo(now()->startOfMinute());
        $order = $this->order();
        $created = $order->created_at->copy();
        $this->travel(3)->days();
        // Pure timestamp logic already knows the correct stage without a write or process.
        $this->assertSame(['Pending', 'Stitching', 'Ready for Verification'], array_column($order->elapsedTransitions(now()), 'to'));
        $this->assertSame('Received', $order->fresh()->status);
        // A route-bound detail request must see the reconciled row, not its old binding.
        $this->getJson(route('orders.show', $order))->assertOk()->assertJsonPath('order.status', 'Ready for Verification');
        $history = $order->statusHistories()->get();
        foreach ([5, 15, 45] as $i => $minutes) {
            $this->assertTrue($history[$i]->created_at->equalTo($created->copy()->addMinutes($minutes)));
            $this->assertSame('System', $history[$i]->actor_name);
        }
        $this->get(route('dashboard'))->assertOk();
        $this->assertSame(3, $order->statusHistories()->count());
        $this->assertSame(1, Notification::where('order_id', $order->id)->where('title', 'Garments Ready for Verification')->count());
        $this->assertSame('Ready for Verification', $order->fresh()->status);
    }

    public function test_status_clock_does_not_depend_on_live_polling(): void
    {
        $order = $this->order();
        $this->travel(46)->minutes();
        $this->getJson(route('live.orders'))->assertOk();
        $this->assertSame('Received', $order->fresh()->status);
        $this->get(route('orders.index'))->assertOk();
        $this->assertSame('Ready for Verification', $order->fresh()->status);
        $this->assertStringNotContainsString('Atelier.poll(refreshOrders', file_get_contents(resource_path('views/orders/index.blade.php')));
        $this->assertArrayNotHasKey('orders:sweep', \Illuminate\Support\Facades\Artisan::all());
    }
}
