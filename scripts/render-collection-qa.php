<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::onceUsingId(App\Models\User::where('is_active',true)->firstOrFail()->id);
$data=app(App\Services\CollectionBoard::class)->data();
file_put_contents(storage_path('app/private/delivery-workflow-qa.html'),view('delivery.index',$data+['statuses'=>App\Services\CollectionNotifications::STATUSES])->render());
file_put_contents(storage_path('app/private/settings-workflow-qa.html'),app(App\Http\Controllers\SettingController::class)->index()->render());
echo json_encode(['stats'=>$data['stats'],'statuses'=>App\Models\Order::selectRaw('status,count(*) as count')->groupBy('status')->get()],JSON_PRETTY_PRINT);
