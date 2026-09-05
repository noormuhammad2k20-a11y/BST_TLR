<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\{SecretSettings,Settings};

final class EncryptSettings extends Command
{
    protected $signature='settings:encrypt-secrets {--apply}';
    protected $description='Convert legacy plaintext secrets without displaying their values';
    public function handle(): int
    {
        $count=0;
        DB::transaction(function () use (&$count) {
            foreach (DB::table('settings')->orderBy('id')->lockForUpdate()->get() as $row) {
                if (!SecretSettings::isSecret($row->key) || !$row->value || str_starts_with($row->value,'enc:v1:')) continue;
                $count++;
                if ($this->option('apply')) DB::table('settings')->where('id',$row->id)->update(['value'=>SecretSettings::encode($row->key,$row->value)]);
            }
        });
        if ($this->option('apply')) Settings::flush();
        $this->info($count.' secret setting(s) '.($this->option('apply')?'encrypted.':'would be encrypted; dry run only.'));
        return self::SUCCESS;
    }
}
