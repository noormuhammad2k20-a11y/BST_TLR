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
        $hours=array_fill_keys(array_keys(Settings::DEFAULT_HOURS),['open'=>true,'from'=>'09:00','to'=>'23:59']);
        Settings::put(['sms_enabled'=>false,'delivery_alert_hours'=>4,'business_hours'=>$hours]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-14 08:00',Settings::timezone()));
        $this->staff = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->staff);
        $this->customer = Customer::create(['name' => 'Workflow test', 'phone' => fake()->unique()->numerify('030########')]);
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

    private function checkAt(string $time): array
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-14 '.$time, Settings::timezone()));
        return app(\App\Services\DeliveryAttentionService::class)->run();
    }

    public function test_morning_opening_and_daily_alerts_are_deduplicated_without_sms(): void
    {
        $order = $this->order(); $order->update(['delivery_date' => '2026-09-14 11:00:00']);
        $this->checkAt('08:30');
        $this->assertSame('Received', $order->fresh()->status);
        $this->assertSame(0, Notification::where('order_id', $order->id)->count());
        $this->checkAt('09:00');
        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertSame('Due in 2h', \App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->assertSame(1, Notification::where('order_id', $order->id)->where('event_key','like','order-due-soon:%')->count());
        Cache::flush(); $this->checkAt('09:01');
        $this->assertSame(1, Notification::where('event_key', 'daily-deliveries:2026-09-14')->count());
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertSame(0, SmsLog::where('order_id', $order->id)->count());
    }

    public function test_afternoon_boundary_future_order_and_overdue_are_delivery_driven(): void
    {
        $order = $this->order(); $order->update(['delivery_date' => '2026-09-14 17:00:00']);
        $future = $this->order(); $future->update(['delivery_date' => '2026-09-15 17:00:00']);
        $this->checkAt('12:59'); $this->assertSame('Stitching',$order->fresh()->status);
        $this->checkAt('13:00'); $this->assertSame('Stitching',$order->fresh()->status);
        $this->assertSame('Due in 4h', \App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->checkAt('17:00'); $this->assertFalse($order->fresh()->is_overdue);
        $this->checkAt('17:25'); $this->assertTrue($order->fresh()->is_overdue);
        $this->assertSame('Overdue by 25m', \App\Services\DeliveryTiming::describe($order->fresh())['text']);
        $this->assertSame('Pending',$future->fresh()->status);
        $this->assertSame('Ready for Verification',$order->fresh()->status);
    }

    public function test_live_endpoint_catches_up_once_without_sms(): void
    {
        $order=$this->order(); $order->update(['delivery_date'=>'2026-09-14 11:00:00']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-14 09:00',Settings::timezone()));
        $this->getJson(route('live.orders'))->assertOk();
        $this->getJson(route('live.orders'))->assertOk();
        $this->assertSame('Pending',$order->fresh()->status);
        $this->assertSame(1,$order->statusHistories()->count());
        $this->artisan('orders:check-deliveries')->assertSuccessful();
        $this->getJson(route('orders.show',$order))->assertOk()->assertJsonPath('order.status','Pending');
    }

    private function enableSms(): void
    {
        $this->committed=true; DB::commit();
        Settings::put(['sms_enabled'=>true,'sms_provider'=>'veevo','veevo_api_key'=>'test-only']);
    }

    public function test_early_ready_sends_once_and_payment_never_delivers(): void
    {
        $order=$this->order(); $order->update(['delivery_date'=>'2026-09-15 17:00:00']);
        $this->enableSms();
        Http::fake(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'early-1'])]);
        $this->postJson(route('orders.notify',$order),['mark_ready'=>true])->assertOk()->assertJsonPath('order.status','Ready')->assertJsonPath('notification.sent',true);
        $this->postJson(route('orders.notify',$order),['mark_ready'=>true])->assertOk()->assertJsonPath('changed',false);
        $this->getJson(route('live.orders'))->assertOk();
        Http::assertSentCount(1);
        $this->assertSame(1,$order->statusHistories()->count());
        $this->assertNotNull($order->fresh()->completed_at);
        $order->refresh()->update(['total'=>0,'balance'=>0]);
        app(OrderService::class)->recalculateBalance($order->fresh());
        $this->assertSame('Ready',$order->fresh()->status);
        $this->checkAt('18:00'); $this->assertFalse($order->fresh()->is_overdue);
        $this->patchJson(route('orders.status',$order),['status'=>'Delivered'])->assertOk();
        $this->assertSame('Delivered',$order->fresh()->status);
    }

    public function test_bulk_ready_counts_failures_skips_and_duplicate_requests(): void
    {
        $orders=collect([$this->order(),$this->order('In Progress'),$this->order(),$this->order('Ready'),$this->order('Delivered')]);
        $this->enableSms();
        Http::fake(['api.veevotech.com/*'=>Http::sequence()->push(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'one'])->push(['STATUS'=>'FAILED'],503)->push(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'three'])]);
        $this->postJson(route('orders.bulk-notify'),['order_ids'=>$orders->pluck('id')->all()])->assertOk()
            ->assertJsonPath('ready',3)->assertJsonPath('sent',2)->assertJsonPath('sms_failed',1)->assertJsonPath('skipped',2);
        $this->postJson(route('orders.bulk-notify'),['order_ids'=>$orders->pluck('id')->all()])->assertOk()->assertJsonPath('ready',0)->assertJsonPath('skipped',5);
        Http::assertSentCount(3);
        foreach($orders->take(3) as $order) $this->assertSame('Ready',$order->fresh()->status);
        $failed=$orders[1]->fresh();
        $this->assertSame('failed',$failed->ready_sms_state);
        $original=$failed->ready_sms_attempt_id;
        Http::swap(new \Illuminate\Http\Client\Factory());
        Http::fake(['api.veevotech.com/*'=>Http::response(['STATUS'=>'SUCCESSFUL','MESSAGE_ID'=>'retry'])]);
        $this->postJson(route('orders.retry-sms',$failed),['attempt_id'=>$original])->assertOk()->assertJsonPath('notification.sent',true);
        $this->postJson(route('orders.retry-sms',$failed),['attempt_id'=>$original])->assertOk();
        $this->assertSame(1,$failed->statusHistories()->count());
        $this->assertSame(2,SmsLog::where('order_id',$failed->id)->where('template_id','order-ready')->count());
    }

    public function test_scheduler_never_marks_ready_or_sends_ready_sms(): void
    {
        $order=$this->order('In Progress');$order->update(['delivery_date'=>'2026-09-14 10:00:00']);
        $ready=$this->order('Ready');$ready->update(['delivery_date'=>'2026-09-14 10:00:00','completed_at'=>'2026-09-14 09:30:00']);
        $this->enableSms();Http::fake();
        $this->checkAt('18:00');$this->checkAt('19:00');
        $this->assertSame('Ready for Verification',$order->fresh()->status);
        $this->assertSame('Ready',$ready->fresh()->status);
        $this->assertFalse($ready->fresh()->is_overdue);
        $this->assertSame('Ready for Pickup',\App\Services\DeliveryTiming::describe($ready->fresh())['indicator']);
        Http::assertNothingSent();
    }

    public function test_create_requires_exact_time_and_returns_received(): void
    {
        $order=app(OrderService::class)->create(['customer_id'=>$this->customer->id,'garment'=>'Shirt','total'=>100,'advance'=>0,
            'delivery_date'=>'2026-09-14','delivery_time'=>'17:00']);
        $this->assertSame('Received',$order->status);
        $this->assertSame('17:00',$order->delivery_date->format('H:i'));
        $this->assertSame(1,$order->statusHistories()->count());
        $this->expectException(ValidationException::class);
        app(OrderService::class)->create(['customer_id'=>$this->customer->id,'garment'=>'Shirt','total'=>100,'advance'=>0,'delivery_date'=>'2026-09-14']);
    }
}
