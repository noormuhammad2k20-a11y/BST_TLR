<?php

use App\Services\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->string('provider_message_id')->nullable()->index();
        });
        Schema::create('whats_app_logs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 50);
            $table->text('message');
            $table->string('template_id', 50)->nullable();
            $table->string('provider', 30)->default('meta');
            $table->string('status', 20);
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->text('api_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        if (! DB::table('settings')->where('key', 'sms_provider')->exists()) {
            $existing = DB::table('settings')->where('key', 'sendpk_api_key')->whereNotNull('value')->where('value', '<>', '')->exists();
            DB::table('settings')->insert(['key' => 'sms_provider', 'value' => $existing ? 'sendpk' : 'veevo',
                'group' => 'sms', 'created_at' => now(), 'updated_at' => now()]);
        }
        // Legacy settings and local previews remain inert; no client history is deleted.
        Settings::flush();
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_logs');
        Schema::table('sms_logs', function (Blueprint $table) {
            $table->dropIndex(['provider_message_id']);
            $table->dropColumn('provider_message_id');
        });
        // Keep the selected provider and all saved credentials.
    }
};
