<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next)
    {
        if ($user=$request->user()) {
            $version=(int)$user->session_version;
            $sessionVersion=$request->session()->get('auth_session_version');
            if (!$user->is_active || ($sessionVersion===null && $version>0) || ($sessionVersion!==null && (int)$sessionVersion!==$version)) {
                Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken();
                return $request->expectsJson() ? response()->json(['message'=>'Your session has expired.'],401) : redirect()->route('login');
            }
            $request->session()->put('auth_session_version',$version);
        }
        return $next($request);
    }
}
