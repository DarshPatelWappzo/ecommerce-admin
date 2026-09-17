<?php

namespace App\Http\Middleware;

use App\Models\TenantDatabase;
use App\Models\UserDomain;
use App\Services\TenantConnectionManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResolveTenant
{
    public function __construct(private readonly TenantConnectionManager $connectionManager) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domainName = Str::of((string) ($request->header('X-Tenant-Domain')
            ?? $request->input('domain')
            ?? ($request->hasSession() ? $request->session()->get('tenant_domain') : null)))
            ->trim()
            ->lower()
            ->toString();

        $tenantDatabase = UserDomain::query()
            ->where('domain_name', $domainName)
            ->with('tenantDatabase')
            ->first()?->tenantDatabase;

        if (! $tenantDatabase instanceof TenantDatabase || $tenantDatabase->status !== 'active') {
            if ($request->routeIs('api.tenant.login')) {
                return response()->json([
                    'message' => 'The provided credentials are incorrect.',
                    'error_code' => 401,
                ], 401);
            }

            throw new NotFoundHttpException('Tenant not found.');
        }

        if ($request->hasSession()) {
            $request->session()->put('tenant_domain', $domainName);
        }
        $request->attributes->set('tenant_database', $tenantDatabase);
        $this->connectionManager->connect($tenantDatabase);

        try {
            return $next($request);
        } finally {
            $this->connectionManager->disconnect();
        }
    }
}
