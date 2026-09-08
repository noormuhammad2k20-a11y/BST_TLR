<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class AuthorizeBusinessRequest
{
    public function handle(Request $request,Closure $next)
    {
        $user=$request->user();
        abort_unless($user && $user->isAdmin(),403,'Owner access is required.');
        return $next($request);
    }
}
