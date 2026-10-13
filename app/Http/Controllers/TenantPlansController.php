<?php

namespace App\Http\Controllers;

use App\Models\TenantDatabase;
use App\Repositories\PackageRepository;
use App\Repositories\PlatformPaymentRepository;
use App\Repositories\TenantRoleRepository;
use App\Services\PlatformGatewayConfigurationService;
use App\Services\TenantSubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class TenantPlansController extends Controller
{
    /** Initialize the central catalog and tenant authorization services. */
    public function __construct(private readonly PackageRepository $packages, private readonly TenantSubscriptionService $subscriptions, private readonly TenantRoleRepository $roles) {}

    /**
     * Display the read-only catalog for the authenticated tenant context.
     *
     * @param  Request  $request  Authenticated tenant request.
     * @return View Plans and current subscription.
     */
    public function index(Request $request, PlatformGatewayConfigurationService $settings, PlatformPaymentRepository $payments): View
    {
        abort_unless($this->roles->userHasPermission($request->user('tenant'), 'plans.view'), 403);
        $tenant = $request->attributes->get('tenant_database');
        abort_unless($tenant instanceof TenantDatabase, 404);
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        $data['packages'] = $this->packages->catalog();
        $data['subscription'] = $this->subscriptions->summary($tenant, $settings->status()['mode']);
        $data['gateway'] = $settings->status();
        $data['canPurchase'] = $request->user('tenant')->roles()->where('slug', 'admin')->where('status', true)->exists();
        $data['payments'] = $payments->history($tenant);
        $data['checkoutScope'] = $tenant->id;

        return view('tenant.plans.index', $data);
    }
}
