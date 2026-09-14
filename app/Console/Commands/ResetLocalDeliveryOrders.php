<?php

namespace App\Console\Commands;

use App\Models\{Customer, Order, User};
use App\Services\{DeliveryAttentionService, DeliveryTiming, NotificationService, OrderService, Settings, StatsService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Auth, DB, Http, Schema, Storage};

final class ResetLocalDeliveryOrders extends Command
{
    protected $signature = 'orders:reset-local-delivery-demo {--database=} {--apply}';
    protected $description = 'Back up and replace ONLY local tailoring order data with five fictional delivery cases';

    public function handle(OrderService $service): int
    {
        $db = DB::connection(); $name = $db->getDatabaseName();
        if (!app()->environment(['local','testing']) || !in_array($db->getConfig('host'), ['127.0.0.1','localhost','::1'], true)
            || !in_array($name, ['test_fnal_telor','atelier_integrity_test'], true) || $this->option('database') !== $name) {
            $this->error('Refusing cleanup: explicit confirmed local database and local/testing environment are required.'); return self::FAILURE;
        }
        $this->info(json_encode(['environment'=>app()->environment(),'database'=>$name,'orders'=>DB::table('orders')->count()]));
        if (!$this->option('apply')) return self::SUCCESS;
        config(['app.timezone'=>Settings::timezone()]); date_default_timezone_set(Settings::timezone());
        Http::fake(['*'=>Http::response([],503)]); // Never send live messages from demo preparation.
        Auth::login(User::where('role','admin')->firstOrFail());
        $preserved = ['products_services'=>'product_services','staff'=>'staff','users'=>'users','expenses'=>'expenses','settings'=>'settings','inventory'=>'cs_products','unrelated_payments'=>'payments'];
        $before=[];
        foreach($preserved as $key=>$table) $before[$key]=$key==='unrelated_payments'?DB::table($table)->whereNull('order_id')->count():DB::table($table)->count();
        $summary = DB::transaction(function () use ($service,$name,$preserved,$before) {
            $orders = DB::table('orders')->lockForUpdate()->get(); $ids=$orders->pluck('id')->all();
            $items=DB::table('order_items')->whereIn('order_id',$ids)->get();
            $pieces=DB::table('order_item_pieces')->whereIn('order_item_id',$items->pluck('id'))->get();
            $owned=DB::table('measurements')->whereIn('order_id',$ids)->orWhereIn('order_item_piece_id',$pieces->pluck('id'))->get();
            $archive=['database'=>$name,'orders'=>$orders,'order_items'=>$items,'order_item_pieces'=>$pieces,'measurements'=>$owned];
            $dependent=['order_status_histories','deliveries','payments','notifications','staff_work_logs','sms_logs','whats_app_logs'];
            foreach($dependent as $table) if(Schema::hasTable($table) && Schema::hasColumn($table,'order_id')) $archive[$table]=DB::table($table)->whereIn('order_id',$ids)->get();
            $archive['activity_logs']=DB::table('activity_logs')->where('subject_type',Order::class)->whereIn('subject_id',$ids)->get();
            $path='order-cleanup/'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.json';
            if (!Storage::disk('local')->put($path,json_encode($archive,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))) throw new \RuntimeException('Backup could not be written; nothing was deleted.');
            // Break order-to-measurement references before deleting exclusively owned sheets.
            DB::table('orders')->whereIn('id',$ids)->update(['measurement_id'=>null]);
            DB::table('measurements')->whereIn('id',$owned->pluck('id'))->delete();
            DB::table('order_item_pieces')->whereIn('id',$pieces->pluck('id'))->delete();
            DB::table('order_items')->whereIn('id',$items->pluck('id'))->delete();
            foreach($dependent as $table) if(isset($archive[$table])) DB::table($table)->whereIn('order_id',$ids)->delete();
            DB::table('activity_logs')->whereIn('id',$archive['activity_logs']->pluck('id'))->delete();
            DB::table('notifications')->where('event_key','like','daily-deliveries:%')->delete();
            DB::table('orders')->whereIn('id',$ids)->delete();
            $now=now()->startOfMinute(); $opening=DeliveryTiming::hours($now)[0] ?? $now->copy()->setTime(9,0);
            $dates=[
                'Morning urgent'=>$opening->copy()->addMinutes(30),
                'Today afternoon'=>$now->copy()->addHours(5)->min($now->copy()->endOfDay()->startOfMinute()),
                'Today late evening'=>$now->copy()->setTime(23,30),
                'Tomorrow'=>$now->copy()->addDay()->setTime(17,0),
                'Overdue'=>$now->copy()->subMinutes(45),
            ];
            $created=[]; $i=0;
            foreach($dates as $label=>$due) {
                $i++; $customer=Customer::firstOrCreate(['phone'=>'LOCAL-DELIVERY-TEST-'.$i],['name'=>'Fictional Demo '.$i]);
                $order=$service->create(['customer_id'=>$customer->id,'garment'=>'Demo Shirt','quantity'=>1,'total'=>1500,'advance'=>0,
                    'delivery_date'=>$due->format('Y-m-d'),'delivery_time'=>$due->format('H:i'),
                    'measurements'=>['length'=>40,'chest'=>38,'waist'=>34,'chest_losing'=>2,'waist_losing'=>2,'hip_losing'=>2],
                    'notes'=>'LOCAL TEST ONLY — '.$label.'; fictional non-dialable contact.']);
                $created[]=['id'=>$order->id,'order'=>$order->display_number,'case'=>$label,'delivery'=>$due->toIso8601String()];
            }
        foreach($preserved as $key=>$table) {
            $after=$key==='unrelated_payments'?DB::table($table)->whereNull('order_id')->count():DB::table($table)->count();
            if($after!==$before[$key]) throw new \RuntimeException('Preservation check failed: '.$key);
        }
        if(DB::table('orders')->count()!==5) throw new \RuntimeException('Expected exactly five local demo orders.');

            return ['deleted_orders'=>count($ids),'backup'=>Storage::disk('local')->path($path),'created'=>$created];
        });
        StatsService::flush(); NotificationService::flushCache();
        $summary['attention']=app(DeliveryAttentionService::class)->run();
        $summary['preserved_counts']=$before;
        $this->info(json_encode($summary,JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}
