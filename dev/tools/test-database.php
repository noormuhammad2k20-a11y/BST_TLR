<?php
// Creates only a fixed, isolated test database. Never resets the configured application database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$source=config('database.connections.mysql');
$test='atelier_integrity_test';
if (($source['database']??'')===$test) throw new RuntimeException('The normal application must not point to the test database.');
$dsn='mysql:host='.$source['host'].';port='.$source['port'].';charset=utf8mb4';
$pdo=new PDO($dsn,$source['username'],$source['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `atelier_integrity_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
config(['database.default'=>'mysql','database.connections.mysql.database'=>$test,'database.connections.mysql.url'=>null]);
Illuminate\Support\Facades\DB::purge('mysql');
if (Illuminate\Support\Facades\DB::connection()->getDatabaseName()!==$test) throw new RuntimeException('Unsafe test database selection.');
if (in_array('--reset-data',$argv,true)) {
    // This switch is intentionally unavailable through Artisan or the application UI.
    // Only the fixed test database above can be reset.
    $connection=Illuminate\Support\Facades\DB::connection();
    $connection->statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        foreach ($connection->select('SHOW TABLES') as $row) {
            $table=array_values((array)$row)[0];
            if ($table==='migrations') continue;
            if (!preg_match('/^[a-z0-9_]+$/i',$table)) throw new RuntimeException('Invalid test table identifier.');
            $connection->table($table)->delete();
        }
    } finally { $connection->statement('SET FOREIGN_KEY_CHECKS=1'); }
    $connection->table('cs_locations')->insert(['name'=>'Main Store','type'=>'Store','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
    echo "Reset isolated test fixtures only.\n";
}
$code=Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
echo Illuminate\Support\Facades\Artisan::output();
exit($code);
