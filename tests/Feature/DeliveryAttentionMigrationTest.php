<?php
namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;

final class DeliveryAttentionMigrationTest extends TestCase
{
    public function test_old_unverified_work_is_not_marked_ready_and_slot_end_becomes_exact_promise(): void
    {
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','database.connections.sqlite.url'=>null]);DB::purge('sqlite');
        Schema::create('orders',function(Blueprint $t){$t->id();$t->string('status');$t->integer('progress')->default(0);$t->dateTime('delivery_date')->nullable();$t->string('time_slot')->nullable();$t->timestamp('notified_at')->nullable();$t->timestamp('ready_sms_attempted_at')->nullable();});
        Schema::create('notifications',fn(Blueprint $t)=>$t->id());
        Schema::create('deliveries',function(Blueprint $t){$t->id();$t->integer('order_id');$t->dateTime('delivery_date')->nullable();});
        Schema::create('order_status_histories',function(Blueprint $t){$t->id();$t->integer('order_id');$t->string('from_status')->nullable();$t->string('to_status');$t->string('label')->nullable();$t->text('note')->nullable();$t->string('actor_name')->nullable();$t->timestamps();});
        (require database_path('migrations/2026_08_07_233018_create_settings_table.php'))->up();
        \App\Services\Settings::flush();
        foreach(['Pending','Stitching','Ready for Verification','Ready','Delivered','Cancelled'] as $status) DB::table('orders')->insert(['status'=>$status,'delivery_date'=>'2026-09-14 00:00:00','time_slot'=>'11:00 AM - 12:00 PM']);
        DB::table('settings')->insert(['key'=>'auto_status_unit','value'=>'hours']);
        (require database_path('migrations/2026_09_11_000001_delivery_attention_workflow.php'))->up();
        $this->assertSame(['Received','In Progress','In Progress','Ready','Delivered','Cancelled'],DB::table('orders')->pluck('status')->all());
        $this->assertSame(6,DB::table('orders')->where('delivery_date','2026-09-14 12:00:00')->count());
        $this->assertSame(3,DB::table('order_status_histories')->count());
        $this->assertFalse(DB::table('settings')->where('key','auto_status_unit')->exists());
        $this->assertSame('4',DB::table('settings')->where('key','delivery_alert_hours')->value('value'));
    }
}
