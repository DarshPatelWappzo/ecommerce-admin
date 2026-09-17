<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TenantDatabase;
use App\Models\User;
use App\Services\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class TenantProvisioningController extends Controller
{
    /**
     * Initialize the controller dependencies.
     *
     * @param  TenantProvisioningService  $tenantProvisioningService  The injected tenant provisioning service.
     */
    public function __construct(private readonly TenantProvisioningService $tenantProvisioningService) {}

    /**
     * Retry provisioning a failed tenant database.
     *
     * @param  User  $user  The user used by this action.
     * @param  TenantDatabase  $tenantDatabase  The tenant database used by this action.
     * @return RedirectResponse The response for this action.
     */
    public function __invoke(User $user, TenantDatabase $tenantDatabase): RedirectResponse
    {
        if ($tenantDatabase->status !== 'failed') {
            return back()->with('error', 'Only failed tenant databases can be retried.');
        }

        $domain = $tenantDatabase->domain;
        abort_if($domain === null, 404);

        $tenantDatabase = $this->tenantProvisioningService->provision($user, $domain, Str::ucfirst(Str::lower(Str::password(5, letters: true, numbers: false, symbols: false))).'@'.random_int(100, 999));

        if ($tenantDatabase->status !== 'active') {
            return back()->with('error', 'Tenant provisioning failed again. Check the application log for details.');
        }

        return back()->with('success', "{$tenantDatabase->database_name} provisioned successfully.");
    }
}
