<?php

namespace Tests\Integration;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\SmsLog;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Services\CustomerNotificationDispatcher;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\Settings;
use App\Services\SmsService;
use App\Services\WhatsAppService;
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
        Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false, 'meta_access_token' => 'test-meta-secret',
            'meta_phone_number_id' => '123', 'meta_waba_id' => '456', 'veevo_api_key' => 'test-veevo-secret',
            'sendpk_api_key' => 'test-sendpk-secret', 'sendpk_sender_id' => 'Approved', 'sms_provider' => 'veevo',
            'meta_templates' => array_map(fn ($t) => ['id' => $t['id'], 'active' => true, 'name' => 'bst_'.str_replace('-', '_', $t['id']),
                'language' => 'en_US', 'parameters' => ['customerName', 'orderID']], Settings::defaultTemplates())]);
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

    private function fakeMeta(array $overrides = []): void
    {
        $templates = array_map(fn ($m) => ['name' => $m['name'], 'language' => 'en_US', 'status' => 'APPROVED',
            'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}, order {{2}}.']]], WhatsAppService::mappings());
        $this->fakeHttp(array_merge([
            'graph.facebook.com/v26.0/456/message_templates*' => Http::response(['data' => $templates]),
            'graph.facebook.com/v26.0/123/messages' => Http::response(['messages' => [['id' => 'wamid.test']]]),
            'graph.facebook.com/v26.0/123?*' => Http::response(['id' => '123', 'display_phone_number' => '+923001234567', 'verified_name' => 'Shop']),
            'api.veevotech.com/*' => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'veevo-1', 'NETWORK_NAME' => 'Test']),
        ], $overrides));
    }

    private function order(): Order
    {
        $customer = Customer::create(['name' => 'Notification customer', 'phone' => '03001234567']);

        return Order::create(['customer_id' => $customer->id, 'total' => '100', 'advance' => '0', 'balance' => '100',
            'status' => 'Ready', 'delivery_date' => now()->addDay(), 'items' => [['name' => 'Shirt', 'qty' => 1, 'price' => 100]]])->load('customer');
    }

    public function test_meta_constructs_all_six_approved_event_payloads_and_logs_ids(): void
    {
        Settings::put(['whatsapp_enabled' => true]);
        $this->fakeMeta();
        $order = $this->order();
        foreach (Settings::defaultTemplates() as $template) {
            $result = WhatsAppService::sendTemplate($template['id'], $order);
            $this->assertTrue($result['sent']);
            $this->assertSame('accepted', $result['status']);
            $this->assertSame('wamid.test', $result['message_id']);
        }
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/messages') && $r->hasHeader('Authorization', 'Bearer test-meta-secret')
            && $r['type'] === 'template' && $r['to'] === '923001234567'
            && $r['template']['components'][0]['parameters'][0]['text'] === 'Notification customer');
        $this->assertSame(6, WhatsAppLog::where('order_id', $order->id)->where('provider_message_id', 'wamid.test')->count());
    }

    public function test_meta_connection_checks_real_endpoint_and_mapping_status(): void
    {
        $this->fakeMeta();
        $r = WhatsAppService::testConnection();
        $this->assertTrue($r['ok']);
        $this->assertNull($r['templates'][0]['error']);
        $this->assertSame('Shop', $r['sender']['name']);
    }

    public function test_meta_rejects_unapproved_wrong_language_unsupported_components_and_parameters(): void
    {
        $m = ['name' => 'ready', 'language' => 'en_US', 'parameters' => ['customerName']];
        $t = ['name' => 'ready', 'language' => 'en_US', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}']]];
        $this->assertNull(WhatsAppService::validateMapping($m, [$t]));
        foreach ([array_replace($t, ['status' => 'PENDING']), array_replace($t, ['language' => 'ur']),
            array_replace($t, ['parameter_format' => 'NAMED']), array_replace($t, ['components' => [['type' => 'HEADER', 'format' => 'IMAGE']]]),
            array_replace($t, ['components' => [['type' => 'BODY', 'text' => 'Hi {{1}} {{2}}']]])] as $bad) {
            $this->assertNotNull(WhatsAppService::validateMapping($m, [$bad]));
        }
    }

    public function test_meta_disabled_missing_credentials_invalid_phone_and_missing_values_do_not_send(): void
    {
        $this->assertFalse(WhatsAppService::sendMapped('order-ready', '03001234567', [])['sent']);
        Settings::put(['whatsapp_enabled' => true, 'meta_access_token' => '']);
        $this->assertFalse(WhatsAppService::sendMapped('order-ready', '03001234567', [])['sent']);
        $this->assertFalse(WhatsAppService::sendMapped('order-ready', 'bad', [])['sent']);
        Http::assertNothingSent();
        Settings::put(['meta_access_token' => 'test-meta-secret']);
        $this->fakeMeta();
        $this->assertFalse(WhatsAppService::sendMapped('order-ready', '03001234567', [])['sent']);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/messages'));
    }

    public function test_meta_failure_is_sanitized_and_does_not_fallback(): void
    {
        Settings::put(['whatsapp_enabled' => true]);
        $this->fakeMeta(['graph.facebook.com/v26.0/123/messages' => Http::response(['error' => ['message' => 'Invalid test-meta-secret', 'code' => 190]], 401)]);
        $r = WhatsAppService::sendTemplate('order-ready', $this->order());
        $this->assertFalse($r['sent']);
        $this->assertArrayNotHasKey('url', $r);
        $this->assertStringNotContainsString('test-meta-secret', json_encode($r));
        $this->assertStringNotContainsString('test-meta-secret', WhatsAppLog::latest('id')->first()->toJson());
    }

    public function test_veevo_payload_message_id_and_logs(): void
    {
        Settings::put(['sms_enabled' => true]);
        $this->fakeMeta();
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
        $this->assertStringNotContainsString('test-meta-secret', json_encode(Settings::forClient()));
        $this->putJson(route('settings.update'), ['sms_provider' => 'unknown'])->assertUnprocessable();
        $this->putJson(route('settings.update'), ['meta_templates' => [['id' => 'bad', 'active' => true, 'name' => 'Bad name', 'language' => 'en_US', 'parameters' => ['unknown']]]])->assertUnprocessable();
        $this->putJson(route('settings.update'), ['meta_templates' => [['id' => 'order-ready', 'active' => true, 'name' => 'static', 'language' => 'en_US', 'parameters' => []]]])->assertOk();
    }

    public function test_settings_require_admin_and_removed_routes_are_absent(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->postJson(route('settings.whatsapp.test'))->assertForbidden();
        $this->putJson(route('settings.update'), ['sms_provider' => 'sendpk'])->assertForbidden();
        $this->assertFalse(app('router')->has('settings.whatsapp.gateway'));
        $this->assertFalse(app('router')->has('settings.whatsapp.gateway.logout'));
    }

    public function test_all_four_channel_combinations_and_after_commit_failure_isolation(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            foreach ([[false, false], [true, false], [false, true], [true, true]] as [$wa,$sms]) {
                Settings::put(['whatsapp_enabled' => $wa, 'sms_enabled' => $sms]);
                $this->fakeMeta();
                $r = CustomerNotificationDispatcher::dispatch('order-ready', $order);
                $this->assertSame($wa, $r['channels']['whatsapp']['sent']);
                $this->assertSame($sms, $r['channels']['sms']['sent']);
            }
            Settings::put(['whatsapp_enabled' => true, 'sms_enabled' => true]);
            $this->fakeMeta(['graph.facebook.com/v26.0/123/messages' => Http::response(['error' => ['message' => 'Unavailable']], 503)]);
            $r = CustomerNotificationDispatcher::dispatch('order-ready', $order);
            $this->assertTrue($r['channels']['sms']['sent']);
            $this->assertFalse($r['channels']['whatsapp']['sent']);
            $this->fakeHttp(['*' => Http::failedConnection()]);
            DB::transaction(function () use ($order) {
                $order->update(['fabric' => 'Notification transaction test']);
                $r = CustomerNotificationDispatcher::dispatch('order-created', $order);
                $this->assertSame('pending', $r['status']);
                Http::assertNothingSent();
            });
            $this->assertSame('Notification transaction test', $order->fresh()->fabric);
            Http::assertSentCount(2);
        } finally {
            Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_logging_failure_does_not_change_api_acceptance(): void
    {
        Settings::put(['sms_enabled' => true]);
        $this->fakeMeta();
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
        $r->assertSee('Meta Access Token', false)->assertSee('Veevo Tech / SPEXT', false)
            ->assertDontSee('test-meta-secret', false)->assertDontSee('gateway-status', false)->assertDontSee('UltraMsg', false);
    }

    public function test_bulk_extension_and_delivery_attempt_sms_once_even_when_meta_fails(): void
    {
        $order = $this->order();
        $delivery = Delivery::create(['order_id' => $order->id, 'status' => 'Ready']);
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true, 'whatsapp_enabled' => true]);
            $this->fakeMeta(['graph.facebook.com/v26.0/123/messages' => Http::response(['error' => ['message' => 'Failed']], 503)]);
            $this->postJson(route('orders.bulk-extend'), ['order_ids' => [$order->id], 'days' => 2, 'reason' => 'Delay'])->assertOk();
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'due-extended')->count());
            $this->postJson(route('delivery.bulk-notify'), ['delivery_ids' => [$delivery->id]])->assertOk()->assertJsonPath('sent', 1);
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
        } finally {
            Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_saved_ready_order_is_not_resent_but_explicit_notify_can_resend(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true]);
            $this->fakeMeta();
            app(OrderService::class)->changeStatus($order, 'Ready');
            Http::assertNothingSent();
            foreach ([1, 2] as $count) {
                $this->postJson(route('orders.notify', $order), [])->assertOk()->assertJsonPath('notification.sent', true);
                $this->assertSame($count, SmsLog::where('order_id', $order->id)->where('template_id', 'order-ready')->count());
            }
        } finally {
            Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_notification_tests_are_rate_limited_and_csrf_protected(): void
    {
        $this->fakeMeta();
        for ($i = 0; $i < 6; $i++) {
            $this->postJson(route('settings.whatsapp.test'))->assertOk();
        }
        $this->postJson(route('settings.whatsapp.test'))->assertStatus(429);
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
            Settings::put(['whatsapp_enabled' => true, 'sms_enabled' => true]);
            $this->fakeHttp(['*' => Http::failedConnection()]);
            $this->postJson(route('orders.store'), ['customer_id' => $customer->id, 'garment' => 'Shirt', 'total' => '100.00', 'advance' => '0.00',
                'delivery_date' => now()->addDay()->toDateString()])->assertCreated();
            $order = Order::where('customer_id', $customer->id)->firstOrFail();
            $this->assertSame('100.00', $order->total);
            $this->assertDatabaseHas('sms_logs', ['order_id' => $order->id, 'template_id' => 'order-created', 'status' => 'failed']);
            $this->fakeMeta();
            $this->postJson(route('payments-billing.record', $order), ['amount' => '40.00', 'payment_method' => 'Cash', 'operation_key' => 'notify-partial-'.$order->id])->assertCreated();
            $this->assertDatabaseHas('sms_logs', ['order_id' => $order->id, 'template_id' => 'payment-received', 'status' => 'accepted']);
            $key = 'notify-final-'.$order->id;
            $this->postJson(route('payments-billing.record', $order), ['amount' => '60.00', 'payment_method' => 'Cash', 'operation_key' => $key])->assertCreated();
            $this->assertSame('0.00', $order->fresh()->balance);
            $this->postJson(route('payments-billing.record', $order), ['amount' => '60.00', 'payment_method' => 'Cash', 'operation_key' => $key])->assertUnprocessable();
            $this->assertSame(1, SmsLog::where('order_id', $order->id)->where('template_id', 'final-receipt')->count());
        } finally {
            Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false]);
            DB::beginTransaction();
        }
    }

    public function test_scheduled_and_page_reminders_share_phone_and_repeat_behavior(): void
    {
        $order = $this->order();
        DB::commit();
        try {
            Settings::put(['sms_enabled' => true, 'whatsapp_enabled' => false, 'alert_days_before' => 2]);
            $this->fakeMeta();
            NotificationService::sweepDueOrders();
            NotificationService::sweepDueOrders();
            $this->assertSame(1,SmsLog::where('order_id',$order->id)->where('template_id','due-reminder')->where('status','accepted')->count());
            $this->assertDatabaseHas('sms_logs',['order_id' => $order->id, 'phone' => '+923001234567']);
        } finally {
            Settings::put(['whatsapp_enabled' => false, 'sms_enabled' => false]);
            DB::beginTransaction();
        }
    }
}
