<?php

namespace Tests\Integration;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\NotificationVariables;
use App\Services\Settings;
use App\Services\SmsService;
use App\Services\SmsTemplateContent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EnglishSmsTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL') !== '1') $this->markTestSkipped('Requires isolated MySQL database.');
        $this->assertSame('atelier_integrity_test', DB::connection()->getDatabaseName());
        DB::beginTransaction();
        Settings::flush();
        Settings::put(['store_name' => 'Best Tailor', 'phone' => '0303 4980786', 'currency' => 'Rs.',
            'date_format' => 'DD/MM/YYYY', 'sms_enabled' => false, 'sms_templates' => []]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL') === '1') while (DB::transactionLevel() > 0) DB::rollBack();
        Settings::flush();
        parent::tearDown();
    }

    private function order(): Order
    {
        $customer = Customer::create(['name' => 'Sana Javed', 'phone' => '03001234567']);

        return Order::create(['order_number' => 'ENGLISH-010', 'customer_id' => $customer->id,
            'total' => '900', 'advance' => '450', 'balance' => '450', 'status' => 'Ready',
            'delivery_date' => '2026-09-15', 'items' => [['name' => 'Alteration and Fitting', 'qty' => 1, 'price' => 900]]])->load('customer');
    }

    public function test_all_six_render_professional_english_with_real_order_variables(): void
    {
        $order = $this->order();
        $variables = NotificationVariables::variablesForOrder($order, [
            'paidAmount' => 'Rs.450', 'oldDate' => '15/09/2026', 'newDate' => '17/09/2026', 'reason' => 'Schedule change',
        ]);
        $id = $order->display_number;
        $expected = [
            'order-created' => "Best Tailor: Dear Sana Javed, your order $id has been received. Alteration and Fitting. Total: Rs 900, Advance: Rs 450, Balance: Rs 450. Due: 15/09/2026. Thank you.",
            'order-ready' => "Best Tailor: Dear Sana Javed, your Alteration and Fitting is ready for collection. Order: $id. Balance due: Rs 450. For assistance, call 0303 4980786.",
            'payment-received' => "Best Tailor: Dear Sana Javed, we have received your payment of Rs 450 for order $id. Remaining balance: Rs 450. Thank you.",
            'due-reminder' => "Best Tailor: Reminder for Sana Javed: Order $id (Alteration and Fitting) is scheduled for delivery on 15/09/2026. Balance due: Rs 450. Contact: 0303 4980786.",
            'due-extended' => "Best Tailor: Dear Sana Javed, the delivery date for order $id has been updated from 15/09/2026 to 17/09/2026. Reason: Schedule change. We apologize for the inconvenience.",
            'final-receipt' => "Best Tailor: Dear Sana Javed, order $id is fully paid. Total: Rs 900. Balance: Rs 0. Thank you for choosing Best Tailor.",
        ];
        foreach ($expected as $event => $message) {
            $values = $event === 'final-receipt' ? array_replace($variables, ['remainingBalance' => 'Rs.0']) : $variables;
            $rendered = SmsService::renderTemplate($event, $values);
            $this->assertSame($message, $rendered);
            $this->assertFalse(SmsTemplateContent::containsRomanUrdu($rendered));
            $this->assertDoesNotMatchRegularExpression('/\{[^}]+\}|undefined|\bnull\b|<[^>]+>| {2}|Rs\.?Rs|Rs\d/', $rendered);
            $this->assertLessThanOrEqual(500, mb_strlen(Settings::activeSmsTemplate($event)['text']));
        }
        $this->assertStringNotContainsString('ready', SmsService::renderTemplate('due-reminder', $variables));
    }

    public function test_optional_values_long_names_and_large_amounts_stay_readable(): void
    {
        $variables = NotificationVariables::variablesForOrder($this->order(), [
            'customerName' => 'Sana   Javed '.str_repeat('Longname ', 10),
            'garmentType' => 'Made-to-measure '.str_repeat('formal ', 10).'suit',
            'shopPhone' => '', 'reason' => '', 'oldDate' => '', 'newDate' => '17/09/2026',
            'totalAmount' => 'Rs.1,234,567', 'remainingBalance' => 'Rs.0',
        ]);
        $created = SmsService::renderTemplate('order-created', $variables);
        $this->assertStringContainsString('Total: Rs 1,234,567', $created);
        $this->assertStringContainsString('Balance: Rs 0', $created);
        $this->assertStringContainsString('Longname Longname', $created);
        foreach (['order-ready', 'due-reminder', 'due-extended'] as $event) {
            $message = SmsService::renderTemplate($event, $variables);
            $this->assertDoesNotMatchRegularExpression('/\{[^}]+\}|undefined|\bnull\b| {2}|Contact:|call \.|Reason:|from  to/', $message);
        }
        $this->assertStringContainsString('updated to 17/09/2026.', SmsService::renderTemplate('due-extended', $variables));
        $this->assertSame('Hello Sana & Ali', NotificationVariables::render('Hello {customerName}', ['customerName' => '<b>Sana</b> &amp; Ali']));
    }

    public function test_forward_upgrade_preserves_custom_english_flags_and_original_archive(): void
    {
        $saved = [
            ['id' => 'order-ready', 'active' => false, 'name' => 'Client title', 'text' => '{shopName}: Aap ka order tayyar hai.'],
            ['id' => 'payment-received', 'active' => true, 'text' => '{shopName}: Thank you for your payment, {customerName}.'],
        ];
        $raw = json_encode($saved);
        DB::table('settings')->updateOrInsert(['key' => 'sms_templates'], ['value' => $raw, 'group' => 'sms']);
        DB::table('settings')->where('key', 'sms_templates_before_english_20260909')->delete();
        $migration = require database_path('migrations/2026_09_09_000001_upgrade_sms_templates_to_english.php');
        $migration->up();
        $after = json_decode(DB::table('settings')->where('key', 'sms_templates')->value('value'), true);
        $this->assertSame(Settings::activeSmsTemplate('order-created')['active'], true);
        $this->assertSame(array_column(Settings::defaultSmsTemplates(), 'text', 'id')['order-ready'], $after[0]['text']);
        $this->assertFalse($after[0]['active']);
        $this->assertSame('Client title', $after[0]['name']);
        $this->assertSame($saved[1], $after[1]);
        $this->assertSame($raw, DB::table('settings')->where('key', 'sms_templates_before_english_20260909')->value('value'));
        $migration->up();
        $migration->down();
        $this->assertSame($after, json_decode(DB::table('settings')->where('key', 'sms_templates')->value('value'), true));
        $this->assertSame('Best Tailor', Settings::str('store_name'));
        $this->assertArrayNotHasKey('sms_templates_before_english_20260909', Settings::forClient());
    }

    public function test_every_original_default_is_upgraded_without_enabling_it(): void
    {
        $legacy = json_decode(file_get_contents(base_path('tests/Fixtures/legacy-sms-defaults.json')), true);
        $this->assertCount(6, $legacy);
        $saved = [];
        foreach ($legacy as $id => $text) $saved[] = ['id' => $id, 'text' => $text, 'active' => false];
        DB::table('settings')->updateOrInsert(['key' => 'sms_templates'], ['value' => json_encode($saved), 'group' => 'sms']);
        (require database_path('migrations/2026_09_09_000001_upgrade_sms_templates_to_english.php'))->up();
        $updated = json_decode(DB::table('settings')->where('key', 'sms_templates')->value('value'), true);
        $defaults = array_column(Settings::defaultSmsTemplates(), 'text', 'id');
        foreach ($updated as $template) {
            $this->assertSame($defaults[$template['id']], $template['text']);
            $this->assertFalse($template['active']);
        }
        $this->assertFalse(Settings::bool('sms_enabled'));
    }

    public function test_restore_defaults_and_saved_template_test_send_use_english(): void
    {
        Settings::put(['sms_templates' => [['id' => 'order-ready', 'text' => 'Custom English notice.']]]);
        $response = $this->postJson(route('settings.reset'), ['group' => 'sms'])->assertOk();
        $this->assertSame(Settings::defaultSmsTemplates(), $response->json('settings.sms_templates'));
        Settings::put(['sms_enabled' => true, 'sms_provider' => 'veevo', 'veevo_api_key' => 'test-key']);
        $templates = Settings::defaultSmsTemplates();
        $templates[1]['text'] = '{shopName}: Please collect order {orderID}, {customerName}. Thank you.';
        $saved = $this->putJson(route('settings.update'), ['sms_templates' => $templates])->assertOk()->json('settings.sms_templates');
        $message = NotificationVariables::render($saved[1]['text'], NotificationVariables::variablesForOrder($this->order()));
        Http::fake(['api.veevotech.com/*' => Http::response(['STATUS' => 'SUCCESSFUL', 'MESSAGE_ID' => 'english-test'])]);
        $this->postJson(route('settings.sms.send-test'), ['phone' => '03001234567', 'message' => $message])->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['textmessage'] === $message && str_contains($r['textmessage'], 'Please collect order'));
        $this->assertDatabaseHas('sms_logs', ['message' => $message, 'provider_message_id' => 'english-test']);
    }

    public function test_retired_text_cannot_be_saved_or_reintroduced_by_a_backup(): void
    {
        $templates = Settings::defaultSmsTemplates();
        $templates[0]['text'] = 'Aap ka order mil gaya. Shukriya!';
        $this->putJson(route('settings.update'), ['sms_templates' => $templates])->assertUnprocessable();
        Settings::put(['sms_templates' => $templates]);
        $this->assertFalse(SmsTemplateContent::containsRomanUrdu(Settings::forClient()['sms_templates'][0]['text']));
        $this->assertFalse(SmsTemplateContent::containsRomanUrdu(SmsService::renderTemplate('order-created', NotificationVariables::variablesForOrder($this->order()))));
    }
}
