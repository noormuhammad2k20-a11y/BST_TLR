<?php

namespace App\Console\Commands;

use App\Services\{DeliveryAttentionService, Settings};
use Illuminate\Console\Command;

final class CheckDeliveryAttention extends Command
{
    protected $signature = 'orders:check-deliveries';
    protected $description = 'Check promised delivery windows and create deduplicated shop alerts';

    public function handle(DeliveryAttentionService $service): int
    {
        config(['app.timezone' => Settings::timezone()]);
        date_default_timezone_set(Settings::timezone());
        $result = $service->run();
        $this->info(json_encode($result));
        return self::SUCCESS;
    }
}
