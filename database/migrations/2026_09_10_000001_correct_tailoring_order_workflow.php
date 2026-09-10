<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('Received')->change();
            // Atomic claim shared by automatic and manual pickup notifications.
            $table->timestamp('ready_sms_attempted_at')->nullable();
        });

        DB::transaction(function () {
            // Old defaults disabled stitching automation. Enable the requested three hops,
            // retaining positive shop-configured delays and the existing time unit.
            $unit = DB::table('settings')->where('key', 'auto_status_unit')->value('value') ?? 'hours';
            foreach (['auto_status_enabled' => 1, 'auto_status_received_delay' => $unit === 'minutes' ? 5 : 1,
                'auto_status_pending_hours' => $unit === 'minutes' ? 60 : 1,
                'auto_status_progress_delay' => $unit === 'minutes' ? 1440 : 24] as $key => $default) {
                $value = DB::table('settings')->where('key', $key)->value('value');
                if ((int) $value <= 0) DB::table('settings')->updateOrInsert(['key' => $key], ['value' => (string) $default, 'group' => 'workflow']);
            }
            DB::table('orders')->whereIn('status', ['In Progress', 'Overdue', 'Completed', 'Trial'])
                ->orderBy('id')->chunkById(200, function ($orders) {
                    foreach ($orders as $order) {
                        $to = match ($order->status) {
                            'Completed' => 'Delivered', 'Trial' => 'Ready for Verification', default => 'Stitching',
                        };
                        if ($order->status === 'Overdue') {
                            $previous = DB::table('order_status_histories')->where('order_id', $order->id)
                                ->where('to_status', 'Overdue')->whereNotNull('from_status')->latest('id')->value('from_status');
                            $previous = ['In Progress' => 'Stitching', 'Trial' => 'Ready for Verification', 'Completed' => 'Delivered'][$previous] ?? $previous;
                            if (in_array($previous, \App\Models\Order::ALL_STATUSES, true)) $to = $previous;
                        }
                        DB::table('orders')->where('id', $order->id)->update([
                            'status' => $to, 'progress' => \App\Models\Order::progressFor($to),
                        ]);
                        DB::table('order_status_histories')->insert([
                            'order_id' => $order->id, 'from_status' => $order->status, 'to_status' => $to,
                            'label' => 'Workflow compatibility migration',
                            'note' => 'Legacy stage mapped; original history preserved.', 'actor_name' => 'System',
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
            // Existing accepted notices must never be sent again by automation.
            DB::table('orders')->where(function ($q) {
                $q->whereNotNull('notified_at')->orWhereIn('id', DB::table('sms_logs')
                    ->select('order_id')->where('template_id', 'order-ready')->whereIn('status', ['accepted', 'sent']));
            })->update(['ready_sms_attempted_at' => now()]);
        });
        \App\Services\Settings::flush();
        \App\Services\StatsService::flush();
    }

    public function down(): void
    {
        // Data/history mappings are intentionally retained; reverting them loses meaning.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('Pending')->change();
            $table->dropColumn('ready_sms_attempted_at');
        });
    }
};
