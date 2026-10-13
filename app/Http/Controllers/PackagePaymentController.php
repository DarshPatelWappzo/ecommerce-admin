<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackagePaymentRequest;
use App\Repositories\PackageRepository;
use App\Repositories\PlatformPaymentRepository;
use App\Services\PackageCheckoutService;
use App\Services\PlatformGatewayConfigurationService;
use App\Services\TenantSubscriptionService;
use Illuminate\Http\JsonResponse;

class PackagePaymentController extends Controller
{
    /** Initialize shared web/API payment services. */
    public function __construct(private readonly PackageCheckoutService $checkout, private readonly PlatformPaymentRepository $payments, private readonly TenantSubscriptionService $subscriptions, private readonly PackageRepository $packages, private readonly PlatformGatewayConfigurationService $settings) {}

    /** Return public catalog fields and selling prices. */
    public function packages(PackagePaymentRequest $request): JsonResponse
    {
        $data = ['data' => $this->packages->catalog()
            ->map(fn($package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'prices' => $package->prices,
                'pricing_basis' => 'fixed_cycle_price'
            ])];

        return response()->json($data);
    }

    /** Reserve or resume an idempotent purchase for the trusted tenant. */
    public function checkout(PackagePaymentRequest $request, int $package): JsonResponse
    {
        $record = $this->checkout->checkout($request->tenant(), $package, $request->validated('billing_cycle'), $request->validated('idempotency_key'));
        $data = ['data' => $this->checkout->response($record)];

        return response()->json($data);
    }

    /** Verify the stored order signature and authoritative capture. */
    public function verify(PackagePaymentRequest $request, int $payment): JsonResponse
    {
        $record = $this->checkout->verify($this->payments->owned($request->tenant(), $payment), $request->validated());
        $data = ['data' => $this->checkout->response($record)];

        return response()->json($data);
    }

    /** Reconcile and return an owned payment without creating an order. */
    public function show(PackagePaymentRequest $request, int $payment): JsonResponse
    {
        $record = $this->checkout->reconcile($this->payments->owned($request->tenant(), $payment));
        $data = ['data' => $this->checkout->response($record)];

        return response()->json($data);
    }

    /** Return the tenant subscription summary. */
    public function subscription(PackagePaymentRequest $request): JsonResponse
    {
        $summary = $this->subscriptions->summary($request->tenant(), $this->settings->status()['mode']);
        $record = $summary['record'];
        $summary['record'] = $record ? [
            'id' => $record->id,
            'package_id' => $record->package_id,
            'name' => $record->price_snapshot['name'] ?? $record->package?->name,
            'billing_cycle' => $record->billing_cycle,
            'starts_at' => $record->start_date,
            'expires_at' => $record->end_date,
            'grace_ends_at' => $record->grace_expires_at,
            'valid_until' => $record->end_date?->copy()->subSecond()->toIso8601String(),
            'mode' => $record->mode ?? 'live',
            'price_snapshot' => $record->price_snapshot,
        ] : null;
        $data = ['data' => $summary];

        return response()->json($data);
    }

    /** Return owned payment history with bounded pagination. */
    public function history(PackagePaymentRequest $request): JsonResponse
    {
        $data = $this->payments->history($request->tenant());

        return response()->json($data);
    }
}
