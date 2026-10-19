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
        $summary = $this->subscriptions->summary($request->tenant(), $this->settings->status()['mode']);
        $start = $summary['status'] === 'Active' ? $summary['record']->end_date : now();
        $data = ['data' => $this->packages->catalog()
            ->map(fn ($package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'grace_period_days' => $package->grace_period_days,
                'prices' => $package->prices,
                'pricing_basis' => 'fixed_cycle_price',
                'purchase_available' => ! $summary['scheduled'] && $start !== null,
                'expected_starts_at' => $start?->toIso8601String(),
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

    /** Record dismissal for an owned checkout without cancelling its provider order. */
    public function dismiss(PackagePaymentRequest $request, int $payment): JsonResponse
    {
        $record = $this->checkout->dismiss($this->payments->owned($request->tenant(), $payment));
        $data = ['data' => $this->checkout->response($record)];

        return response()->json($data);
    }

    /** Return the tenant subscription summary. */
    public function subscription(PackagePaymentRequest $request): JsonResponse
    {
        $summary = $this->subscriptions->summary($request->tenant(), $this->settings->status()['mode']);
        foreach (['record', 'scheduled'] as $key) {
            $record = $summary[$key];
            $summary[$key] = $record ? [
                'id' => $record->id,
                'package_id' => $record->package_id,
                'name' => $record->price_snapshot['name'] ?? $record->package?->name,
                'billing_cycle' => $record->billing_cycle,
                'starts_at' => $record->start_date,
                'expires_at' => $record->end_date,
                'grace_ends_at' => $record->grace_expires_at,
                'grace_period_days' => $record->grace_period_days,
                'is_in_grace_period' => $this->subscriptions->status($record) === 'Grace Period',
                'remaining_grace_days' => $this->subscriptions->remainingGraceDays($record),
                'valid_until' => $record->end_date?->copy()->subSecond()->toIso8601String(),
                'mode' => $record->mode ?? 'live',
                'price_snapshot' => $record->price_snapshot,
            ] : null;
        }
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
