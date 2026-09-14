<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['app.timezone'=>App\Services\Settings::timezone()]);
date_default_timezone_set(App\Services\Settings::timezone());
Illuminate\Support\Facades\Auth::login(App\Models\User::where('is_active',true)->firstOrFail());
$controller=app(App\Http\Controllers\OrderController::class);
file_put_contents(storage_path('app/order-auto-qa.html'),$controller->index()->render());
file_put_contents(storage_path('app/order-auto-qa.json'),$controller->live()->getContent());
echo "Rendered order page and live payload for local QA.\n";
