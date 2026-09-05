<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled maintenance
|--------------------------------------------------------------------------
| Optional: if you run `php artisan schedule:work`, order statuses stay
| current even when nobody has the app open. Without the scheduler the same
| sweep still runs (rate-limited) whenever a page is loaded.
*/
Schedule::command('orders:sweep')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
