<?php

namespace App\Http\Middleware;

use App\Services\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the shop's General settings to the runtime for the duration of the
 * request, so a timezone or language chosen in Settings genuinely changes how
 * the whole application behaves rather than only how the Settings page looks.
 */
class ApplyShopSettings
{
    public function handle(Request $request, Closure $next): Response
    {
        // Timezone. Config is what Carbon, Eloquent casts and `now()` read.
        $timezone = Settings::timezone();
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);

        // Language. Drives translation lookups and the document direction.
        $locale = Settings::locale();
        app()->setLocale($locale);
        Date::setLocale($locale);

        return $next($request);
    }
}
