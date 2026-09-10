<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\CatalogueIdentity;
class ReconcileCatalogue extends Command
{
    protected $signature = 'catalogue:reconcile {--apply}';
    protected $description = 'Preview canonical catalogue identities; preserve duplicate rows as aliases';
    public function handle(): int
    {
        foreach (CatalogueIdentity::reconcile((bool)$this->option('apply')) as $row) $this->line(json_encode($row));
        return self::SUCCESS;
    }
}
