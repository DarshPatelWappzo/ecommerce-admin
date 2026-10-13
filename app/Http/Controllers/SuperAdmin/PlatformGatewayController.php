<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlatformGatewayRequest;
use App\Models\PlatformGatewayVersion;
use App\Repositories\PlatformPaymentRepository;
use App\Services\PlatformGatewayConfigurationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformGatewayController extends Controller
{
    /** Initialize the platform settings service. */
    public function __construct(private readonly PlatformGatewayConfigurationService $settings) {}

    /** Show configuration presence without returning credentials. */
    public function index(PlatformGatewayRequest $request, PlatformPaymentRepository $payments): View
    {
        $data = [];
        $data['settings'] = $this->settings->status();
        $data['resolutionPayments'] = $payments->requiringResolution();
        $data['versions'] = PlatformGatewayVersion::query()->select(['id', 'mode', 'created_at'])->latest('id')->get();

        return view('super-admin.platform-payments.index', $data);
    }

    /** Save validated credentials as a new encrypted version. */
    public function update(PlatformGatewayRequest $request): RedirectResponse
    {
        $this->settings->save($request->validated(), $request->user('web')->id);

        return redirect()->route('super-admin.platform-payments.index')->with('success', 'Payment configuration saved. Configure the version-specific webhook URL in Razorpay.');
    }
}
