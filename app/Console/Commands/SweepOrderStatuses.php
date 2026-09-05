<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class SweepOrderStatuses extends Command
{
    protected $signature = 'orders:sweep';

    protected $description = 'Auto-start queued orders and alert on overdue deliveries';

    public function handle(OrderService $orders): int
    {
        // Every automated hop the shop has switched on, each with its own delay
        // in Settings → Workflow. Stages left at zero are not touched.
        $started = $orders->autoAdvanceOrders(force: true);

        // Lateness is reported, never written over the status — a garment can
        // be overdue while it is still on the machine.
        $overdue = $orders->flagOverdueOrders(force: true);

        $this->info("Auto-advanced {$started} order(s).");
        $this->info("Flagged {$overdue} order(s) as overdue.");

        return self::SUCCESS;
    }
}
