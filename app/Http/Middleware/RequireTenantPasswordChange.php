<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTenantPasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('tenant')->user()?->is_first_login && ! $request->routeIs('tenant.password.change', 'tenant.password.form')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Password change required before accessing the tenant application.'], 403);
            }

            return redirect()->route('tenant.password.form');
        }

        return $next($request);
    }
}
