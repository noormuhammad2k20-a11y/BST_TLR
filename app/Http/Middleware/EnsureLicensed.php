<?php

namespace App\Http\Middleware;

use App\Services\Licensing\LicenseChecker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicensed
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('license.show', 'license.install', 'logout', 'pwa.manifest')) {
            return $next($request);
        }
        $result = app(LicenseChecker::class)->check();
        if (!$result->valid()) {
            return $request->expectsJson()
                ? response()->json(['message' => $result->message, 'code' => 'license_required', 'activation_url' => route('license.show')], 403)->header('Cache-Control', 'no-store, private')
                : redirect()->route('license.show')->header('Cache-Control', 'no-store, private');
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
