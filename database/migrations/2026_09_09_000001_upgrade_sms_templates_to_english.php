<?php

use App\Services\Settings;
use App\Services\SmsTemplateContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $row = DB::table('settings')->where('key', 'sms_templates')->lockForUpdate()->first();
            if (! $row) return;
            $saved = json_decode($row->value ?? '', true);
            if (! is_array($saved)) return;
            $defaults = array_column(Settings::defaultSmsTemplates(), 'text', 'id');
            $changed = false;
            foreach ($saved as &$template) {
                if (! is_array($template) || ! isset($defaults[$template['id'] ?? ''])) continue;
                if (SmsTemplateContent::containsRomanUrdu($template['text'] ?? '')) {
                    $template['text'] = $defaults[$template['id']];
                    $changed = true;
                }
            }
            unset($template);
            if (! $changed) return;

            // Retain the exact original for audit/recovery without exposing it
            // as an active template or overwriting an existing archive.
            DB::table('settings')->insertOrIgnore([
                'key' => 'sms_templates_before_english_20260909', 'value' => $row->value,
                'group' => 'archive', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('settings')->where('key', 'sms_templates')->update([
                'value' => json_encode($saved, JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ]);
        });
        Settings::flush();
    }

    public function down(): void
    {
        // Content upgrade is intentionally retained: rollback must not restore
        // retired defaults or overwrite English edits made after deployment.
    }
};
