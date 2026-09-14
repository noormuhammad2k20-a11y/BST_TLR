<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Preserve collected orders and legacy proof of notification. Repair only
        // open Ready rows for which no successful customer notice was recorded.
        DB::transaction(function () {
            $orders = DB::table('orders')->where('status', 'Ready')->whereNull('delivered_at')
                ->whereNull('notified_at')->whereNull('deleted_at')->lockForUpdate()->get();
            foreach ($orders as $order) {
                $sent = DB::table('collection_sms_orders as links')->join('sms_logs as sms', 'sms.id', '=', 'links.sms_log_id')
                    ->where('links.order_id', $order->id)->whereIn('sms.status', ['accepted','sent','delivered'])->whereNotNull('sms.sent_at')->exists();
                if ($sent) continue;
                DB::table('orders')->where('id', $order->id)->update(['status'=>'Ready for Verification','progress'=>90,'edit_version'=>$order->edit_version + 1,'updated_at'=>now()]);
                DB::table('deliveries')->where('order_id', $order->id)->update(['status'=>'Scheduled','updated_at'=>now()]);
                DB::table('order_status_histories')->insert(['order_id'=>$order->id,'from_status'=>'Ready','to_status'=>'Ready for Verification',
                    'label'=>'Collection notification required','note'=>'No successful Ready SMS was recorded. Verify and notify before collection.',
                    'actor_name'=>'System','created_at'=>now(),'updated_at'=>now()]);
            }
        });
    }

    public function down(): void
    {
        // A rollback must never restore an unnotified order to Ready.
    }
};
