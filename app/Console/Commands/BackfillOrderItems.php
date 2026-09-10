<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderItemsBackfill;
use Illuminate\Console\Command;

class BackfillOrderItems extends Command
{
    protected $signature = 'orders:backfill-items {--apply : Persist verified backfill} {--order= : Process one order ID}';
    protected $description = 'Preview or apply relational order backfill without changing financial history';

    public function handle(OrderItemsBackfill $backfill): int
    {
        Order::withTrashed()->when($this->option('order'), fn($q) => $q->whereKey($this->option('order')))
            ->orderBy('id')->chunkById(100, function ($orders) use ($backfill) {
                foreach ($orders as $order) $this->line(json_encode($backfill->run($order, (bool)$this->option('apply')), JSON_UNESCAPED_UNICODE));
            });
        return self::SUCCESS;
    }
}
