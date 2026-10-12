<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePaymentGatewayRequest;
use App\Repositories\TenantRoleRepository;
use App\Services\OrderPaymentGatewayException;
use App\Services\PaymentGatewayService;
use App\Services\RazorpayOrderGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantPaymentGatewayController extends Controller
{
    /** Initialize tenant settings and permission services. */
    public function __construct(private readonly PaymentGatewayService $settings, private readonly TenantRoleRepository $roles) {}

    /**
     * Show only safe configuration values and version-specific webhook URLs.
     *
     * @param  SavePaymentGatewayRequest  $request  Authorized tenant request.
     * @return View Settings page.
     */
    public function index(SavePaymentGatewayRequest $request): View
    {
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        $data['settings'] = $this->settings->status($request->attributes->get('tenant_database')?->id);
        $data['canEdit'] = $this->roles->userHasPermission($data['tenantUser'], 'payment_gateways.update');

        return view('tenant.payment-gateways.index', $data);
    }

    /**
     * Save encrypted settings without returning secrets.
     *
     * @param  SavePaymentGatewayRequest  $request  Validated form.
     * @return RedirectResponse Success notification.
     */
    public function update(SavePaymentGatewayRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated(), $request->user('tenant'));

        return redirect()->route('tenant.payment-gateways.index')->with('success', 'Payment gateway settings saved. Update Razorpay webhook URLs when a credential version changes.');
    }

    /**
     * Remove only an unused archived version from this tenant.
     *
     * @param  SavePaymentGatewayRequest  $request  Authorized settings administrator.
     * @param  int  $version  Tenant-local version identifier.
     * @return RedirectResponse Safe removal result.
     */
    public function retire(SavePaymentGatewayRequest $request, int $version): RedirectResponse
    {
        $this->settings->retireUnusedVersion($version, $request->user('tenant'));

        return redirect()->route('tenant.payment-gateways.index')->with('success', 'Unused historical credential version removed.');
    }

    /**
     * Authenticate saved credentials with a read-only provider request.
     *
     * @param  SavePaymentGatewayRequest  $request  Selected saved environment.
     * @param  RazorpayOrderGateway  $gateway  Provider client.
     * @return JsonResponse Safe authentication result.
     */
    public function testConnection(SavePaymentGatewayRequest $request, RazorpayOrderGateway $gateway): JsonResponse
    {
        try {
            $gateway->testConnection($this->settings->savedVersion($request->validated('mode')));
            $data = ['message' => 'API authentication succeeded. Webhook delivery and signatures have not been tested.'];

            return response()->json($data);
        } catch (OrderPaymentGatewayException) {
            $data = ['message' => 'The saved API credentials could not be loaded. Save the Key ID and Key Secret for the selected environment and retry. A webhook secret is not required for this test.'];

            return response()->json($data, 422);
        }
    }
}
