<?php

namespace Tests\Integration;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\CustomerNotificationDispatcher;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\Settings;
use App\Services\SmsService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class OfficialNotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL') !== '1') {
            $this->markTestSkipped('Requires the isolated atelier_integrity_test MySQL database.');
        }
        $this->assertSame('atelier_integrity_test', DB::connection()->getDatabaseName());
        DB::beginTransaction();
        Settings::flush();
        Cache::flush();
        Http::preventStrayRequests();
        Settings::put(['sms_enabled' => false, 'veevo_api_key' => 'test-veevo-secret',
            'sendpk_api_key' => 'test-sendpk-secret', 'sendpk_sender_id' => 'Approved', 'sms_provider' => 'veevo']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL') === '1') {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
        }
        Settings::flush();
        parent::tearDown();
    }

    private function fakeHttp(array $responses): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function fakeSms(array $overrides = []): void
    {
        $this->fakeHttp(array_merge([
            'api.veevotech.com/*' => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'veevo-1', 'NETWORK_NAME' => 'Test']),
        ], $overrides));
    }

    private function order(): Order
    {
        $customer = Customer::create(['name' => 'Notification customer', 'phone' => '03001234567']);

        return Order::create(['customer_id' => $customer->id, 'total' => '100', 'advance' => '0', 'balance' => '100',
            'status' => 'Ready', 'delivery_date' => now()->addDay(), 'items' => [['name' => 'Shirt', 'qty' => 1, 'price' => 100]]])->load('customer');
    }

    public function test_every_event_dispatches_one_sms_with_recipient_content_and_log(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true]);
            foreach (Settings::defaultSmsTemplates() as $template) {
                $this->fakeSms();
                $extra = ['paidAmount' => '40', 'newDate' => '15/09/2026', 'reason' => 'Delay'];
                $message = SmsService::renderTemplate($template['id'], \App\Services\NotificationVariables::variablesForOrder($order, $extra));
                $result = CustomerNotificationDispatcher::dispatch($template['id'], $order, $extra);
                $this->assertSame(['sms'], array_keys($result['channels']));
                $this->assertTrue($result['sent']);
                Http::assertSentCount(1);
                Http::assertSent(fn ($r) => $r['receivernum'] === '+923001234567' && $r['textmessage'] === $message);
                $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', $template['id'])->count());
                $this->assertDatabaseHas('sms_logs', ['order_id' => $order->id, 'customer_id' => $order->customer_id,
                    'template_id' => $template['id'], 'message' => $message, 'provider' => 'veevo', 'status' => 'accepted']);
            }
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_manual_customer_sms_validates_logs_and_preserves_original_phone(): void
    {
        Settings::put(['sms_enabled' => true]);
        $customer = $this->order()->customer;
        $url = route('customers.sms', $customer);
        $this->fakeSms();
        foreach (['', '   ', str_repeat('x', 2001)] as $message) {
            $this->postJson($url, compact('message'))->assertUnprocessable();
        }
        Http::assertNothingSent();
        $this->postJson($url, ['message' => 'Your order is ready.'])->assertOk()->assertJsonPath('success', true);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('sms_logs', ['customer_id' => $customer->id, 'message' => 'Your order is ready.', 'status' => 'accepted']);
        $this->assertSame('03001234567', $customer->fresh()->phone);
        $this->fakeHttp(['*' => Http::failedConnection()]);
        $this->postJson($url, ['message' => 'Provider failure'])->assertUnprocessable();
        Http::assertSentCount(1);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->postJson($url, ['message' => 'Forbidden'])->assertForbidden();
    }

    public function test_invalid_inputs_disabled_templates_and_retired_configuration_cannot_send(): void
    {
        Settings::put(['sms_enabled' => true, 'whatsapp_enabled' => true, 'meta_access_token' => 'retired-secret']);
        $this->fakeSms();
        foreach (['bad', '', '0300123456', '+12345678901'] as $phone) {
            $this->assertFalse(SmsService::send($phone, 'Test')['sent']);
        }
        foreach (['', '  ', str_repeat('x', 2001)] as $message) {
            $this->assertFalse(SmsService::send('03001234567', $message)['sent']);
        }
        Settings::put(['sms_templates' => [['id' => 'order-ready', 'active' => false]]]);
        $this->assertNull(SmsService::sendTemplate('order-ready', $this->order()));
        $this->assertArrayNotHasKey('whatsapp_enabled', Settings::forClient());
        $this->assertArrayNotHasKey('meta_access_token', Settings::forClient());
        foreach (['test', 'test-template', 'send-test', 'gateway', 'gateway.logout'] as $suffix) {
            $this->assertFalse(app('router')->has('settings.whatsapp.'.$suffix));
        }
        Http::assertNothingSent();
    }

    public function test_rollback_discards_deferred_sms(): void
    {
        Settings::put(['sms_enabled' => true]);
        $order = $this->order();
        $this->fakeSms();
        DB::beginTransaction();
        CustomerNotificationDispatcher::dispatch('order-created', $order);
        DB::rollBack();
        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::where('order_id', $order->id)->count());
    }

    public function test_core_pages_render_with_sms_only_controls(): void
    {
        foreach (['dashboard', 'customers.index', 'orders.index', 'measurements.index', 'payments-billing.index',
            'expenses.index', 'reports.index', 'settings.index', 'delivery.index', 'notifications.index',
            'cloth-store.dashboard', 'cloth-store.checkout.index', 'cloth-store.settings.index'] as $name) {
            $response = $this->get(route($name));
            $response->assertOk()->assertDontSee('wa.me', false)->assertDontSee('data-type="whatsapp"', false);
            if (getenv('SMS_RENDER_CHECK') === '1') {
                $directory = base_path('dev/artifacts/sms-ui');
                if (! is_dir($directory)) mkdir($directory, 0755, true);
                file_put_contents($directory.'/'.$name.'.html', $response->getContent());
            }
        }
    }

    public function test_veevo_payload_message_id_and_logs(): void
    {
        Settings::put(['sms_enabled' => true]);
        $this->fakeSms();
        $r = SmsService::send('0300-1234567', 'Test SMS');
        $this->assertTrue($r['sent']);
        $this->assertSame('veevo-1', $r['message_id']);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.veevotech.com/v3/sendsms' && $r->method() === 'POST'
            && $r['receivernum'] === '+923001234567' && $r['apikey'] === 'test-veevo-secret' && ! isset($r['sendernum']));
        $this->assertDatabaseHas('sms_logs', ['provider' => 'veevo', 'provider_message_id' => 'veevo-1', 'status' => 'accepted']);
    }

    public function test_veevo_missing_key_error_and_network_failure(): void
    {
        Settings::put(['sms_enabled' => true, 'veevo_api_key' => '']);
        $this->assertFalse(SmsService::send('03001234567', 'Test')['sent']);
        Http::assertNothingSent();
        Settings::put(['veevo_api_key' => 'test-veevo-secret']);
        $this->fakeHttp(['*' => Http::response(['STATUS' => 'FAILED', 'ERROR_CODE' => 'KEY', 'ERROR_DESCRIPTION' => 'test-veevo-secret rejected'])]);
        $r = SmsService::send('03001234567', 'Test');
        $this->assertFalse($r['sent']);
        $this->assertStringNotContainsString('test-veevo-secret', json_encode($r));
        $this->fakeHttp(['*' => Http::failedConnection()]);
        $this->assertFalse(SmsService::send('03001234567', 'Test')['sent']);
    }

    public function test_sendpk_uses_current_post_endpoints_unicode_and_balance(): void
    {
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'sendpk']);
        $this->fakeHttp(['sendpk.com/api/sms.php' => Http::response('OK ID:12345'), 'sendpk.com/api/balance.php' => Http::response('100.50')]);
        $r = SmsService::send('+923001234567', 'شکریہ');
        $this->assertTrue($r['sent']);
        Http::assertSent(fn ($r) => $r->url() === 'https://sendpk.com/api/sms.php' && $r->method() === 'POST'
            && $r['type'] === 'unicode' && $r['mobile'] === '923001234567' && $r['sender'] === 'Approved');
        $this->assertSame('100.50', SmsService::balance()['balance']);
        $this->assertDatabaseHas('sms_logs', ['provider' => 'sendpk', 'provider_message_id' => '12345']);
    }

    public function test_provider_rejections_and_malformed_responses_are_not_successes(): void
    {
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'sendpk']);
        foreach (['8', 'garbage', 'OK', '{"error":"bad"}'] as $body) {
            $this->fakeHttp(['*' => Http::response($body)]);
            $this->assertFalse(SmsService::send('03001234567', 'Test')['sent']);
        }
        Settings::put(['sms_provider' => 'veevo']);
        $this->fakeHttp(['*' => Http::response(['STATUS' => 'SUCCESSFUL'])]);
        $this->assertFalse(SmsService::send('03001234567', 'Test')['sent']);
    }

    public function test_veevo_capabilities_do_not_invent_remote_validation(): void
    {
        $this->assertFalse(SmsService::testConnection()['verified']);
        $this->assertFalse(SmsService::balance()['ok']);
        Http::assertNothingSent();
    }

    public function test_settings_encrypt_mask_preserve_providers_and_reject_bad_mapping(): void
    {
        $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk'])->assertOk();
        $this->putJson(route('settings.update'), ['sms_provider' => 'veevo', 'veevo_api_key' => str_repeat('•', 12)])->assertOk();
        $this->assertSame('test-veevo-secret', Settings::str('veevo_api_key'));
        $this->assertSame('test-sendpk-secret', Settings::str('sendpk_api_key'));
        $this->assertStringStartsWith('enc:v1:', DB::table('settings')->where('key', 'veevo_api_key')->value('value'));
        $this->assertStringNotContainsString('test-veevo-secret', json_encode(Settings::forClient()));
        $this->putJson(route('settings.update'), ['sms_provider' => 'unknown'])->assertUnprocessable();
    }

    public function test_settings_require_admin_and_removed_routes_are_absent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->postJson(route('settings.sms.test'))->assertForbidden();
        $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk'])->assertForbidden();
        $this->assertFalse(app('router')->has('settings.whatsapp.gateway'));
        $this->assertFalse(app('router')->has('settings.whatsapp.gateway.logout'));
    }

    public function test_sms_only_and_after_commit_failure_isolation(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            foreach ([false, true] as $enabled) {
                $order = $this->order(); // Each pickup event receives one attempt.
                Settings::put(['sms_enabled' => $enabled]);
                $this->fakeSms();
                $r = CustomerNotificationDispatcher::dispatch('order-ready', $order);
                $this->assertSame(['sms'], array_keys($r['channels']));
                $this->assertSame($enabled, $r['channels']['sms']['sent']);
                Http::assertSentCount($enabled ? 1 : 0);
            }
            $this->fakeHttp(['*' => Http::failedConnection()]);
            DB::transaction(function () use ($order) {
                $order->update(['fabric' => 'Notification transaction test']);
                $r = CustomerNotificationDispatcher::dispatch('order-created', $order);
                $this->assertSame('pending', $r['status']);
                Http::assertNothingSent();
            });
            $this->assertSame('Notification transaction test', $order->fresh()->fabric);
            Http::assertSentCount(1);
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_logging_failure_does_not_change_api_acceptance(): void
    {
        Settings::put(['sms_enabled' => true]);
        $this->fakeSms();
        SmsLog::creating(function () {
            throw new \RuntimeException('Simulated log failure');
        });
        try {
            $this->assertTrue(SmsService::send('03001234567', 'Test')['sent']);
        } finally {
            SmsLog::flushEventListeners();
        }
    }

    public function test_reminder_repeat_guard_is_shared_and_atomic(): void
    {
        Settings::put(['repeat_alerts' => true, 'repeat_alert_hours' => 3]);
        $this->assertTrue(NotificationService::mayRepeat('notification-test'));
        $this->assertFalse(NotificationService::mayRepeat('notification-test'));
        $this->travel(4)->hours();
        $this->assertTrue(NotificationService::mayRepeat('notification-test'));
    }

    public function test_settings_screen_renders_without_old_provider_controls_or_tokens(): void
    {
        $r = $this->get(route('settings.index'));
        $r->assertOk();
        $r->assertDontSee('WhatsApp', false)->assertDontSee('Meta Access Token', false)->assertSee('Veevo Tech / SPEXT', false)
            ->assertDontSee('test-veevo-secret', false)->assertDontSee('gateway-status', false)->assertDontSee('UltraMsg', false);
    }

    public function test_bulk_extension_and_delivery_attempt_sms_once(): void
    {
        $order = $this->order();
        $delivery = Delivery::create(['order_id' => $order->id, 'status' => 'Ready']);
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true]);
            $this->fakeSms();
            $this->postJson(route('orders.bulk-extend'), ['order_ids' => [$order->id], 'days' => 2, 'reason' => 'Delay'])->assertOk();
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'due-extended')->count());
            $this->postJson(route('delivery.bulk-notify'), ['delivery_ids' => [$delivery->id]])->assertOk()->assertJsonPath('sent', 1);
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_saved_ready_order_and_explicit_notify_do_not_duplicate_pickup_sms(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true]);
            $this->fakeSms();
            app(OrderService::class)->changeStatus($order, 'Ready');
            Http::assertNothingSent();
            foreach ([1, 1] as $count) {
                $this->postJson(route('orders.notify', $order), [])->assertOk()->assertJsonPath('notification.sent', true);
                $this->assertSame($count, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
            }
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_notification_tests_are_rate_limited_and_csrf_protected(): void
    {
        $this->fakeSms();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('settings.sms.test'))->assertOk();
        }
        $this->postJson(route('settings.sms.test'))->assertStatus(429);
        app()->detectEnvironment(fn () => 'local');
        try {
            $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk'])->assertStatus(419);
        } finally {
            app()->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_active_sms_provider_validation_does_not_require_inactive_credentials(): void
    {
        $this->putJson(route('settings.update'), ['sms_enabled' => true, 'sms_provider' => 'veevo', 'sendpk_api_key' => ''])->assertOk();
        $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk'])->assertUnprocessable();
        $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk', 'sendpk_api_key' => 'new-key', 'sendpk_sender_id' => ''])->assertUnprocessable();
    }

    public function test_order_and_payment_commits_survive_provider_failure_and_final_replay_does_not_resend(): void
    {
        $customer = Customer::create(['name' => 'Order flow customer', 'phone' => '03001234567']);
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true]);
            $this->fakeHttp(['*' => Http::failedConnection()]);
            $this->postJson(route('orders.store'), ['customer_id' => $customer->id, 'garment' => 'Shirt', 'total' => '100.00', 'advance' => '0.00',
                'measurements' => ['length'=>40,'chest'=>38,'waist'=>34,'chest_losing'=>2,'waist_losing'=>2,'hip_losing'=>2],
                'delivery_date' => now()->addDay()->toDateString()])->assertCreated();
            $order = Order::where('customer_id', $customer->id)->firstOrFail();
            $this->assertSame('100.00', $order->total);
            $this->assertDatabaseHas('sms_logs', ['order_id' => $order->id, 'template_id' => 'order-created', 'status' => 'failed']);
            $this->fakeSms();
            $this->postJson(route('payments-billing.record', $order), ['amount' => '40.00', 'payment_method' => 'Cash', 'operation_key' => 'notify-partial-'.$order->id])->assertCreated();
            $this->assertDatabaseHas('sms_logs', ['order_id' => $order->id, 'template_id' => 'payment-received', 'status' => 'accepted']);
            $key = 'notify-final-'.$order->id;
            $this->postJson(route('payments-billing.record', $order), ['amount' => '60.00', 'payment_method' => 'Cash', 'operation_key' => $key])->assertCreated();
            $this->assertSame('0.00', $order->fresh()->balance);
            $this->postJson(route('payments-billing.record', $order), ['amount' => '60.00', 'payment_method' => 'Cash', 'operation_key' => $key])->assertUnprocessable();
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'final-receipt')->count());
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_scheduled_and_page_reminders_share_phone_and_repeat_behavior(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true, 'alert_days_before' => 2]);
            $this->fakeSms();
            NotificationService::sweepDueOrders();
            NotificationService::sweepDueOrders();
            $this->assertSame(1,SmsLog::where('order_id',$order->id)->where('template_id','due-reminder')->where('status','accepted')->count());
            $this->assertDatabaseHas('sms_logs',['order_id' => $order->id, 'phone' => '+923001234567']);
        } finally {
            Settings::put(['sms_enabled' => false]);
            DB::beginTransaction();
        }
    }
}
