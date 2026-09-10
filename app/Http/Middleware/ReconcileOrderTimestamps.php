<?php

namespace App\Http\Middleware;

use App\Models\Order;
use App\Services\OrderService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ReconcileOrderTimestamps
{
    public function handle(Request $request, Closure $next): Response
    {
        // Existing counter/notification polling is not an order-status clock.
        // Normal page visits and user actions reconcile before filters/reports run.
        if (!$request->routeIs('live.*')) {
            app(OrderService::class)->reconcileElapsedOrders();
            // SubstituteBindings may already have loaded a previous persisted stage.
            foreach ($request->route()->parameters() as $parameter) {
                if ($parameter instanceof Order) $parameter->refresh();
            }
        }
        return $next($request);
    }
}
