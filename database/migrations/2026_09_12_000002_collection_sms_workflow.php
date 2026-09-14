<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $t) {
            $t->string('reason',32)->nullable()->index();
            $t->uuid('attempt_key')->nullable()->unique();
        });
        Schema::create('collection_sms_orders', function (Blueprint $t) {
            $t->foreignId('sms_log_id')->constrained('sms_logs')->restrictOnDelete();
            $t->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $t->string('reason',32);
            $t->primary(['sms_log_id','order_id']);
        });
        // A durable per-phone mutex serializes claims across tabs, workers and
        // legacy customer duplicates. It contains no phone or other personal data.
        Schema::create('collection_sms_locks', function (Blueprint $t) {
            $t->string('phone_hash',64)->primary();
        });
        DB::table('sms_logs')->where('template_id','order-ready')->whereNotNull('order_id')->orderBy('id')
            ->chunkById(200,function ($logs) {
                foreach ($logs as $log) {
                    if (!DB::table('orders')->where('id',$log->order_id)->exists()) continue;
                    DB::table('sms_logs')->where('id',$log->id)->update(['reason'=>'collection-first']);
                    DB::table('collection_sms_orders')->insert(['sms_log_id'=>$log->id,'order_id'=>$log->order_id,'reason'=>'collection-first']);
                }
            });
    }
    public function down(): void
    {
        throw new RuntimeException('Collection SMS history is retained. Use a forward migration rather than dropping sent-message records.');
    }
};
