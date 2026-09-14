<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use App\Services\{DeliveryTiming, Settings};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'ready_sms_state')) $table->string('ready_sms_state', 16)->nullable();
            if (!Schema::hasColumn('orders', 'ready_sms_attempt_id')) $table->uuid('ready_sms_attempt_id')->nullable();
        });
        if (!Schema::hasColumn('notifications', 'event_key')) Schema::table('notifications', fn (Blueprint $table) => $table->string('event_key', 160)->nullable()->unique());
        DB::transaction(function () {
            DB::table('orders')->orderBy('id')->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    $updates = [];
                    $status = ['Pending' => 'Received', 'Stitching' => 'In Progress', 'Ready for Verification' => 'In Progress',
                        'Trial' => 'In Progress', 'Confirmed' => 'Received', 'Quality Check' => 'In Progress', 'Completed' => 'Delivered'][$order->status] ?? $order->status;
                    if ($status !== $order->status) {
                        $updates['status'] = $status;
                        $updates['progress'] = ['Received' => 0, 'In Progress' => 40, 'Ready' => 100, 'Delivered' => 100][$status];
                        DB::table('order_status_histories')->insert(['order_id' => $order->id, 'from_status' => $order->status,
                            'to_status' => $status, 'label' => 'Delivery workflow migration', 'note' => 'Physical readiness is preserved; unverified work remains In Progress.',
                            'actor_name' => 'System', 'created_at' => now(), 'updated_at' => now()]);
                    }
                    // Existing datetime column remains authoritative. Legacy broad slots
                    // use their latest promised time; missing slots retain end-of-day semantics.
                    if ($order->delivery_date && substr($order->delivery_date, 11) === '00:00:00') {
                        $updates['delivery_date'] = substr($order->delivery_date, 0, 10).' '.(DeliveryTiming::slotTime($order->time_slot) ?? '23:59').':00';
                        DB::table('deliveries')->where('order_id', $order->id)->update(['delivery_date' => $updates['delivery_date']]);
                    }
                    if ($order->notified_at) $updates['ready_sms_state'] = 'sent';
                    elseif ($order->ready_sms_attempted_at) $updates['ready_sms_state'] = 'failed';
                    if ($order->ready_sms_attempted_at) $updates['ready_sms_attempt_id'] = (string) \Illuminate\Support\Str::uuid();
                    if ($updates) DB::table('orders')->where('id', $order->id)->update($updates);
                }
            });
            DB::table('settings')->whereIn('key', ['auto_status_enabled','auto_status_received_delay','auto_status_unit','auto_status_pending_hours',
                'auto_status_progress_delay','auto_status_verify_delay','auto_status_ready_delay','auto_delivery_update','at_risk_hours','delivery_slots','alert_days_before'])->delete();
            DB::table('settings')->updateOrInsert(['key' => 'delivery_alert_hours'], ['value' => '4', 'group' => 'workflow']);
        });
        Settings::flush();
        \App\Services\StatsService::flush();
    }
    public function down(): void
    {
        // Do not undo semantic data mappings or erase their audit history.
        Schema::table('notifications', function (Blueprint $table) { $table->dropUnique(['event_key']); $table->dropColumn('event_key'); });
        Schema::table('orders', function (Blueprint $table) { $table->dropColumn(['ready_sms_state','ready_sms_attempt_id']); });
    }
};
