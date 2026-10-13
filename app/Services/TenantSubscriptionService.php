<?php

namespace App\Services;

use App\Models\PackagePayment;
use App\Models\TenantDatabase;
use App\Models\UserPackage;
use App\Repositories\TenantSubscriptionRepository;
use Illuminate\Validation\ValidationException;

class TenantSubscriptionService
{
    public function __construct(private readonly TenantSubscriptionRepository $subscriptions) {}

    public function assertPurchasable(TenantDatabase $tenant, int $packageId, string $mode): void
    {
        $current = $this->subscriptions->currentForPurchase($tenant, $mode);
        if ($current && (int) $current->package_id !== $packageId) {
            throw ValidationException::withMessages(['package' => 'Package changes are unavailable. Renew your current package.']);
        }
        if ($current && $current->end_date === null) {
            throw ValidationException::withMessages(['package' => 'This subscription has no recorded expiry. Contact support before renewing.']);
        }
    }

    /** Called only under the central owner and payment locks after authoritative capture. */
    public function activatePayment(TenantDatabase $tenant, PackagePayment $payment): UserPackage
    {
        $existing = UserPackage::query()->where('package_payment_id', $payment->id)->first();
        if ($existing) {
            return $existing;
        }
        $current = $this->subscriptions->currentForPurchase($tenant, $payment->mode);
        $start = $current && (int) $current->package_id === (int) $payment->package_id && $current->end_date?->gt($payment->paid_at)
            ? $current->end_date->copy() : $payment->paid_at->copy();
        $end = $start->copy()->addMonthsNoOverflow($payment->quote['duration_months']);

        return UserPackage::create([
            'user_id' => $tenant->user_id,
            'tenant_database_id' => $tenant->id,
            'package_id' => $payment->package_id,
            'package_payment_id' => $payment->id,
            'mode' => $payment->mode,
            'price_snapshot' => $payment->quote,
            'billing_cycle' => $payment->billing_cycle,
            'start_date' => $start,
            'end_date' => $end,
            'grace_expires_at' => $end->copy()->addDays(7),
            'status' => 'active',
        ]);
    }

    /** @return array{record: UserPackage|null, status: string, remaining_days: int|null} */
    public function summary(TenantDatabase $tenant, string $mode = 'live'): array
    {
        $records = $this->subscriptions->forTenant($tenant)->filter(fn(UserPackage $record): bool => $record->mode === null || $record->mode === $mode);
        $record = $records->first(fn(UserPackage $record): bool => in_array($this->status($record), ['Active', 'Grace Period'], true)) ?? $records->first();

        return [
            'record' => $record,
            'status' => $record ? $this->status($record) : 'No Subscription',
            'remaining_days' => $record?->end_date ? max(0, (int) ceil(now()->diffInDays($record->end_date, false))) : null,
        ];
    }

    public function status(UserPackage $record): string
    {
        if (in_array($record->status, ['cancelled', 'canceled'], true)) {
            return 'Cancelled';
        }
        if (in_array($record->status, ['active', 'grace_period'], true)) {
            if (($record->end_date !== null && $record->end_date->lte(now())) && $record->grace_expires_at?->isFuture()) {
                return 'Grace Period';
            }
            if ($record->status === 'active' && ($record->end_date === null || $record->end_date->isFuture())) {
                return 'Active';
            }
        }

        return 'Expired';
    }
}
