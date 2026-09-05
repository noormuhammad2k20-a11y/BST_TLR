<?php
// Real independent MySQL connections/processes. Uses only the isolated test database.
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'mysql','database.connections.mysql.database'=>'atelier_integrity_test','database.connections.mysql.url'=>null,
    'cache.default'=>'array','session.driver'=>'array','queue.default'=>'sync']);
Illuminate\Support\Facades\DB::purge('mysql');
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\ClothStore\{Customer,Category,Product,Location,Order,CustomerPayment};
use App\Services\ClothStore\{InventoryService,FinanceService,ReturnService};
use Symfony\Component\Process\Process;

if (DB::connection()->getDatabaseName()!=='atelier_integrity_test') throw new RuntimeException('Unsafe database.');
$root=realpath(__DIR__.'/../..');
$dir=$root.'/dev/artifacts';if (!is_dir($dir)) mkdir($dir,0777,true);
if (($argv[1]??'')==='worker') {
    $job=json_decode(file_get_contents($argv[2]),true);auth()->loginUsingId($job['user']);
    file_put_contents($argv[2].'.ready','1');
    $deadline=microtime(true)+15;
    while (!file_exists($job['gate'])) { if (microtime(true)>$deadline) throw new RuntimeException('Barrier timeout.');usleep(10000); }
    try {
        switch ($job['operation']) {
            case 'payment': app(FinanceService::class)->collect($job['customer'],['amount'=>'100.00','payment_method'=>'Cash']);break;
            case 'reverse': app(FinanceService::class)->reverse($job['payment']);break;
            case 'return': app(ReturnService::class)->transition($job['return'],'Approved');break;
            case 'checkout':
                $request=Illuminate\Http\Request::create('/cloth-store/checkout','POST',$job['data']);
                $result=app(App\Http\Controllers\ClothStore\CheckoutController::class)->store($request);
                if ($result->getStatusCode()!==200) throw new Illuminate\Validation\ValidationException(validator([],[]));
                break;
        }
        echo 'accepted';
    } catch (Illuminate\Validation\ValidationException $e) { echo 'rejected'; }
    exit;
}

function pair(array $job): void {
    global $dir;
    $key=bin2hex(random_bytes(8));$job['gate']=$dir.'/'.$key.'.gate';$processes=[];$paths=[];
    foreach ([1,2] as $index) {
        $file=$dir.'/'.$key.'-'.$index.'.json';file_put_contents($file,json_encode($job));$paths[]=$file;
        $process=new Process([PHP_BINARY,__FILE__,'worker',$file]);$process->setTimeout(30);$process->start();$processes[]=$process;
    }
    $deadline=microtime(true)+20;
    while (!file_exists($paths[0].'.ready') || !file_exists($paths[1].'.ready')) {
        if (microtime(true)>$deadline) throw new RuntimeException('Workers failed to reach barrier.');usleep(10000);
    }
    file_put_contents($job['gate'],'go');$outputs=[];
    foreach ($processes as $process) {$process->wait();if (!$process->isSuccessful()) throw new RuntimeException($process->getErrorOutput().$process->getOutput());$outputs[]=trim($process->getOutput());}
    sort($outputs);
    if ($outputs!==['accepted','rejected']) throw new RuntimeException('Race failed: '.json_encode($outputs));
    echo $job['operation'].': one accepted, one rejected'.PHP_EOL;
    foreach ($paths as $file) {unlink($file);unlink($file.'.ready');}unlink($job['gate']);
}
function check(bool $condition,string $message): void {if (!$condition) throw new RuntimeException($message);}
$user=User::factory()->create(['role'=>'admin','is_active'=>true]);auth()->login($user);
$customer=Customer::create(['name'=>'Concurrency fixture','phone'=>'RACE-'.uniqid(),'due_balance'=>'100.00']);
$order=Order::create(['cs_customer_id'=>$customer->id,'invoice_number'=>'RACE-'.uniqid(),'subtotal'=>'100','total_amount'=>'100','paid_amount'=>'0','remaining_amount'=>'100']);
pair(['operation'=>'payment','user'=>$user->id,'customer'=>$customer->id]);
check($customer->fresh()->due_balance==='0.00','Payment race left invalid balance.');
$payment=CustomerPayment::where('cs_customer_id',$customer->id)->firstOrFail();
pair(['operation'=>'reverse','user'=>$user->id,'payment'=>$payment->id]);
check($customer->fresh()->due_balance==='100.00','Reversal ran more than once.');
$category=Category::create(['name'=>'Concurrency fabric']);
$product=Product::create(['cs_category_id'=>$category->id,'name'=>'Concurrency cotton','price'=>'10','cost_price'=>'4','stock_quantity'=>'0','status'=>'Active']);
app(InventoryService::class)->move($product->id,'10.00','Fixture','race');
$saleCustomer=Customer::create(['name'=>'Race till','phone'=>'RACE-'.uniqid(),'due_balance'=>'0']);
$data=['cs_customer_id'=>$saleCustomer->id,'items'=>[['cs_product_id'=>$product->id,'quantity'=>'7.00']],'paid_amount'=>'70.00','payment_method'=>'Cash'];
pair(['operation'=>'checkout','user'=>$user->id,'data'=>$data]);check($product->fresh()->stock_quantity==='3.00','Oversold stock.');
$sale=Order::where('cs_customer_id',$saleCustomer->id)->firstOrFail();
$return=app(ReturnService::class)->create(['order_id'=>$sale->id,'items'=>[['order_item_id'=>$sale->items()->first()->id,'quantity'=>'7.00','reason'=>'Race','action_type'=>'Refund']]]);
pair(['operation'=>'return','user'=>$user->id,'return'=>$return->id]);check($product->fresh()->stock_quantity==='10.00','Return restored twice.');
echo 'All four synchronized MySQL race scenarios passed. Fixtures retained only in atelier_integrity_test.'.PHP_EOL;
