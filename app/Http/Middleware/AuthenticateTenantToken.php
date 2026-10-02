<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant\User;
use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTenantToken
{
    public function __construct(private readonly TenantConnectionManager $connectionManager) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = $request->bearerToken();
        $token = $plainTextToken ? PersonalAccessToken::findToken($plainTextToken) : null;

        if (! $token || $token->tokenable_type !== (new User)->getMorphClass()
            || ! $token->expires_at || $token->expires_at->isPast()
            || ! $token->can('tenant-api')) {
            return response()->json(['message' => 'Your access token is missing, invalid or expired.', 'error_code' => 401], 401);
        }

        $tenantDatabase = $token->tenantDatabase;

        if (! $tenantDatabase || $tenantDatabase->status !== 'active' || ! $tenantDatabase->domain) {
            return response()->json(['message' => 'Your access token is missing, invalid or expired.', 'error_code' => 401], 401);
        }

        $this->connectionManager->connect($tenantDatabase);

        try {
            $user = Auth::guard('sanctum')->user();

            if (! $user instanceof User || $user->status !== 'active') {
                return response()->json(['message' => 'Your access token is missing, invalid or expired.', 'error_code' => 401], 401);
            }

            return $next($request);
        } finally {
            Auth::forgetGuards();
            $this->connectionManager->disconnect();
        }
    }
}
