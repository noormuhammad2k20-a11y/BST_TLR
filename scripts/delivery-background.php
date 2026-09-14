<?php

// Private, windowless entry point. Never dispatch an Artisan/console command here.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('LARAVEL_START', microtime(true));
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__.'/../storage/logs/delivery-background-errors.log');

$lock = null;
$app = null;
try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    // A local file lock is released by the OS even if the process crashes.
    $lock = fopen($app->storagePath('framework/delivery-background.lock'), 'c');
    if ($lock === false) {
        throw new RuntimeException('Cannot open delivery background lock.');
    }
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        exit(0);
    }
    config(['app.timezone' => App\Services\Settings::timezone()]);
    date_default_timezone_set(App\Services\Settings::timezone());
    $result = $app->make(App\Services\DeliveryAttentionService::class)->run();
    $status = json_encode(['completed_at' => now()->toIso8601String(), 'result' => $result], JSON_THROW_ON_ERROR);
    if (file_put_contents($app->storagePath('framework/delivery-background-status.json'), $status, LOCK_EX) === false) {
        throw new RuntimeException('Cannot save delivery background status.');
    }
} catch (Throwable $exception) {
    error_log((string) $exception);
    exit(1);
} finally {
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

exit(0);
