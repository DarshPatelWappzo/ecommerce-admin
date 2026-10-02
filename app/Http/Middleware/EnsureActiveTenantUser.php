<?php

namespace App\Http\Middleware;

use App\Models\Tenant\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveTenantUser
{
    /**
     * End inactive tenant sessions before allowing authenticated web access.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('tenant');
        $user = $guard->user();

        if (! $user instanceof User || $user->status !== 'active') {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', ['tenant'], route('tenant.login.form'));
        }

        return $next($request);
    }
}
