<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\{OrderService, Settings};
use Illuminate\Console\Command;

final class BackfillTailorWork extends Command
{
    protected $signature = 'orders:backfill-tailor-work {--apply : Create missing credits}';
    protected $description = 'Repair missing assigned-tailor work credits for Ready and delivered orders';

    public function handle(OrderService $service): int
    {
        config(['app.timezone'=>Settings::timezone()]);
        date_default_timezone_set(Settings::timezone());
        $count=0;
        Order::whereIn('status',['Ready','Delivered','Completed'])->whereNotNull('staff_id')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('staff_work_logs')->whereColumn('staff_work_logs.order_id','orders.id'))
            ->chunkById(100, function($orders) use ($service,&$count) {
                foreach($orders as $order) {
                    $this->line($order->display_number.' — '.$order->staff?->name);
                    if (!$this->option('apply') || $service->reconcileStaffWork($order)) $count++;
                }
            });
        $this->info($count.($this->option('apply') ? ' missing credits created.' : ' candidates; use --apply to repair.'));
        return self::SUCCESS;
    }
}
