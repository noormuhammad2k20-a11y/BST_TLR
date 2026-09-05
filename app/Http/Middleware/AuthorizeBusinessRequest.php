<?php
namespace App\Http\Middleware;

use App\Services\Access;
use Closure;
use Illuminate\Http\Request;

final class AuthorizeBusinessRequest
{
    public function handle(Request $request,Closure $next)
    {
        $user=$request->user(); $name=(string)$request->route()?->getName();
        if (!$user || $user->isAdmin()) return $next($request);
        if (str_starts_with($name,'cloth-store.')) {
            $permission=Access::permission($request);
            abort_unless($permission && Access::allowed($user,$permission),403,'You do not have permission for this action.');
            if ($name==='cloth-store.checkout.store' && $request->input('discount',0)>0) abort_unless(Access::allowed($user,'Sales - Discount'),403);
            return $next($request);
        }
        if (str_starts_with($name,'profile.') || $name==='logout') return $next($request);
        if ($user->role==='tailor') {
            return app(\App\Http\Controllers\TailorWorkspaceController::class)->dispatchAuthorized($request,$next);
        }
        // Root staff role gives tailoring counter access, not administration or destructive finance.
        $allowed=['dashboard','live.dashboard','live.counters','live.notifications','live.orders','search',
            'customers.index','customers.store','customers.update','customers.summary',
            'orders.index','orders.show','orders.store','orders.update','orders.status','orders.receipt','orders.notify','orders.bulk-notify',
            'orders.bulk-status','orders.bulk-extend','measurements.index','measurements.store','measurements.show','measurements.update',
            'products-services.index','payments-billing.index','payments-billing.record','payments-billing.invoice',
            'delivery.index','delivery.status','delivery.bulk-notify','notifications.index','notifications.read','notifications.read-all',
            'printing-center.index','printing-center.render'];
        abort_unless(in_array($name,$allowed,true),403,'Administrator permission is required.');
        if (in_array($name,['orders.status','orders.bulk-status']) && $request->input('status')==='Cancelled') abort(403);
        return $next($request);
    }
}
