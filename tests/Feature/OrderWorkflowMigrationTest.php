<?php

namespace Tests\Feature;

use App\Services\Settings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

final class OrderWorkflowMigrationTest extends TestCase
{
    public function test_legacy_mapping_preserves_data_history_and_positive_settings(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->string('status')->default('Pending'); $table->integer('progress')->default(0);
            $table->timestamp('notified_at')->nullable(); $table->string('notes')->nullable();
        });
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->string('from_status')->nullable();
            $table->string('to_status'); $table->string('label')->nullable(); $table->string('note')->nullable();
            $table->string('actor_name')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->string('template_id'); $table->string('status');
        });
        foreach (['In Progress', 'Overdue', 'Completed', 'Cancelled', 'Ready'] as $status) {
            DB::table('orders')->insert(['status' => $status, 'notes' => 'Preserve me']);
        }
        DB::table('order_status_histories')->insert(['order_id' => 1, 'to_status' => 'In Progress']);
        DB::table('sms_logs')->insert(['order_id' => 5, 'template_id' => 'order-ready', 'status' => 'accepted']);
        DB::table('settings')->insert([
            ['key' => 'auto_status_pending_hours', 'value' => '3'],
            ['key' => 'auto_status_progress_delay', 'value' => '0'],
        ]);
        (require database_path('migrations/2026_09_10_000001_correct_tailoring_order_workflow.php'))->up();
        $this->assertSame(['Stitching', 'Stitching', 'Delivered', 'Cancelled', 'Ready'], DB::table('orders')->pluck('status')->all());
        $this->assertSame(5, DB::table('orders')->where('notes', 'Preserve me')->count());
        $this->assertDatabaseHas('order_status_histories', ['order_id' => 1, 'to_status' => 'In Progress']);
        $this->assertSame(4, DB::table('order_status_histories')->count());
        $this->assertNotNull(DB::table('orders')->where('id', 5)->value('ready_sms_attempted_at'));
        $this->assertSame(3, Settings::int('auto_status_pending_hours'));
        $this->assertSame(24, Settings::int('auto_status_progress_delay'));
        $id = DB::table('orders')->insertGetId(['notes' => 'Default test']);
        $this->assertSame('Received', DB::table('orders')->where('id', $id)->value('status'));
        Settings::flush();
    }
}
