<?php

namespace App\Http\Middleware;

use App\Models\PersonalAccessToken;
use App\Models\Tenant\Customer;
use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCustomerToken
{
    public function __construct(private readonly TenantConnectionManager $connections) {}

    /** @param Closure(Request): Response $next Authenticated customer action. */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ? PersonalAccessToken::findToken($request->bearerToken()) : null;
        if (
            ! $token || $token->tokenable_type !== (new Customer)->getMorphClass() || ! $token->can('customer-api') || ! $token->expires_at || $token->expires_at->isPast()
            || ! $token->tenantDatabase || $token->tenantDatabase->status !== 'active' || ! $token->tenantDatabase->domain
        ) {
            return response()->json(['message' => 'Your access token is missing, invalid or expired.', 'error_code' => 401], 401);
        }
        $this->connections->connect($token->tenantDatabase);
        try {
            $customer = Customer::where('status', 'active')->find($token->tokenable_id);
            if (! $customer) {
                return response()->json(['message' => 'Your access token is missing, invalid or expired.', 'error_code' => 401], 401);
            }
            $customer->withAccessToken($token);
            $request->setUserResolver(fn() => $customer);
            $token->forceFill(['last_used_at' => now()])->save();

            return $next($request);
        } finally {
            $this->connections->disconnect();
        }
    }
}
