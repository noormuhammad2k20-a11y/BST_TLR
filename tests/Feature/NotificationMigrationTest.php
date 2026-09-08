<?php

namespace Tests\Feature;

use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class NotificationMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        (require database_path('migrations/2026_08_25_135245_create_sms_logs_table.php'))->up();
        Settings::flush();
    }

    private function upgrade(): void
    {
        (require database_path('migrations/2026_09_06_000002_add_official_notification_logs.php'))->up();
    }

    public function test_fresh_install_defaults_to_veevo(): void
    {
        $this->upgrade();
        $this->assertSame('veevo', Settings::str('sms_provider'));
        $this->assertTrue(Schema::hasTable('whats_app_logs'));
        $this->assertTrue(Schema::hasColumn('sms_logs', 'provider_message_id'));
    }

    public function test_legacy_sendpk_credentials_templates_and_records_survive(): void
    {
        DB::table('settings')->insert([
            ['key' => 'sendpk_api_key', 'value' => 'legacy-secret'], ['key' => 'sms_templates', 'value' => '[{"id":"order-ready","text":"Saved"}]'],
            ['key' => 'gateway_token', 'value' => 'retired-secret'], ['key' => 'store_name', 'value' => 'Existing shop'],
        ]);
        DB::table('sms_logs')->insert(['phone' => '03001234567', 'message' => 'Historical SMS', 'status' => 'sent']);
        $this->upgrade();
        $this->assertSame('sendpk', Settings::str('sms_provider'));
        $this->assertDatabaseHas('settings', ['key' => 'sendpk_api_key', 'value' => 'legacy-secret']);
        $this->assertDatabaseHas('sms_logs', ['message' => 'Historical SMS']);
        $this->assertArrayNotHasKey('gateway_token', Settings::forClient());
        $this->assertDatabaseHas('settings', ['key' => 'store_name', 'value' => 'Existing shop']);
    }

    public function test_explicit_provider_selection_is_never_overwritten(): void
    {
        DB::table('settings')->insert([['key' => 'sms_provider', 'value' => 'sendpk'], ['key' => 'sendpk_api_key', 'value' => '']]);
        $this->upgrade();
        $this->assertSame('sendpk', Settings::str('sms_provider'));
        (require database_path('migrations/2026_09_06_000002_add_official_notification_logs.php'))->down();
        $this->assertDatabaseHas('settings', ['key' => 'sms_provider', 'value' => 'sendpk']);
    }
}
