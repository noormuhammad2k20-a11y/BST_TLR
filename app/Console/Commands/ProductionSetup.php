<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Setting;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class ProductionSetup extends Command
{
    protected $signature='production:setup';
    protected $description='Provision required settings and permissions; create an administrator without demo data';
    public function handle(): int
    {
        $this->call('db:seed',['--class'=>\Database\Seeders\ProductionPermissionsSeeder::class,'--force'=>true]);
        foreach (Settings::SCHEMA as $key=>$meta) if (!Setting::where('key',$key)->exists() && empty($meta['secret'])) {
            Setting::put($key,Settings::encode($key,$meta['default']),$meta['group']??'general');
        }
        if (User::where('role','admin')->where('is_active',true)->exists()) {
            $this->info('Existing administrator retained. No user passwords changed.'); return self::SUCCESS;
        }
        $email=$this->ask('Administrator email'); $name=$this->ask('Administrator name'); $password=$this->secret('Administrator password (minimum 12 characters)');
        Validator::make(compact('email','name','password'),['email'=>'required|email|unique:users','name'=>'required|string|max:255','password'=>'required|string|min:12'])->validate();
        User::create(['email'=>$email,'name'=>$name,'password'=>$password,'role'=>'admin','is_active'=>true]);
        $this->info('Administrator created. No demonstration data seeded.');return self::SUCCESS;
    }
}
