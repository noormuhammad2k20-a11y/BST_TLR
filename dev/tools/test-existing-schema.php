<?php
// Replays pending migrations against a disposable schema clone of the working database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

$source=config('database.connections.mysql');
$sourceName=$source['database'];
$targetName='atelier_existing_schema_test';
if ($sourceName===$targetName || !preg_match('/^[a-zA-Z0-9_]+$/',$sourceName)) throw new RuntimeException('Unsafe source database selection.');
$binary='D:/Xamp/mysql/bin/mysqldump.exe';
$command=[$binary,'--no-data','--skip-comments','--host='.$source['host'],'--port='.$source['port'],'--user='.$source['username']];
if (filled($source['password'])) $command[]='--password='.$source['password'];
$command[]=$sourceName;
$dump=new Process($command);$dump->setTimeout(60);$dump->run();
if (!$dump->isSuccessful()) throw new RuntimeException('Schema export failed without exposing credentials: '.$dump->getErrorOutput());
$pdo=new PDO('mysql:host='.$source['host'].';port='.$source['port'].';charset=utf8mb4',$source['username'],$source['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('DROP DATABASE IF EXISTS `atelier_existing_schema_test`');
$pdo->exec('CREATE DATABASE `atelier_existing_schema_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$target=new PDO('mysql:host='.$source['host'].';port='.$source['port'].';dbname='.$targetName.';charset=utf8mb4',$source['username'],$source['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$target->exec($dump->getOutput());
foreach (DB::table('migrations')->orderBy('id')->get() as $migration) {
    $target->prepare('INSERT INTO migrations (migration,batch) VALUES (?,?)')->execute([$migration->migration,$migration->batch]);
}
config(['database.default'=>'mysql','database.connections.mysql.database'=>$targetName,'database.connections.mysql.url'=>null]);
DB::purge('mysql');
if (DB::connection()->getDatabaseName()!==$targetName) throw new RuntimeException('Unsafe migration target.');
$code=Artisan::call('migrate',['--force'=>true]);
echo Artisan::output();
exit($code);
