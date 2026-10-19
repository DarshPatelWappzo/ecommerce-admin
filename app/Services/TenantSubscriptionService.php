<?php

namespace App\Services;

use App\Models\Package;
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
        if ($this->subscriptions->scheduled($tenant, $mode)) {
            throw ValidationException::withMessages(['package' => 'A paid package is already scheduled. Another future purchase is unavailable until it starts.']);
        }
        $current = $this->subscriptions->currentForPurchase($tenant, $mode);
        if ($current && $this->status($current) === 'Active' && $current->end_date === null) {
            throw ValidationException::withMessages(['package' => 'This subscription has no recorded expiry. Contact support before purchasing.']);
        }
    }

    /** Called under the central owner and payment locks. Conflicts retain the captured payment for resolution. */
    public function activatePayment(TenantDatabase $tenant, PackagePayment $payment): ?UserPackage
    {
        $existing = UserPackage::query()
            ->where('package_payment_id', $payment->id)
            ->first();
        if ($existing) {
            return $existing;
        }
        $newerPaid = PackagePayment::query()
            ->where('tenant_database_id', $tenant->id)
            ->where('mode', $payment->mode)
            ->where('id', '>', $payment->id)
            ->where('status', 'captured')
            ->exists();
        $current = $this->subscriptions->currentForPurchase($tenant, $payment->mode);
        if (
            $newerPaid || $this->subscriptions->scheduled($tenant, $payment->mode)
            || ($current && $this->status($current) === 'Active' && $current->end_date === null)
        ) {
            $payment->update(['failure_code' => 'resolution_required']);

            return null;
        }
        $start = $current && $this->status($current) === 'Active' && $current->end_date?->gt($payment->paid_at)
            ? $current->end_date->copy() : $payment->paid_at->copy();
        $end = $start->copy()->addMonthsNoOverflow($payment->quote['duration_months']);
        $graceDays = Package::on(config('subscriptions.connection'))->withTrashed()
            ->whereKey($payment->package_id)->lockForUpdate()->firstOrFail()->grace_period_days;

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
            'grace_period_days' => $graceDays,
            'grace_expires_at' => $end->copy()->addDays($graceDays),
            'status' => $start->isFuture() ? 'scheduled' : 'active',
        ]);
    }

    /** @return array{record: UserPackage|null, scheduled: UserPackage|null, status: string, remaining_days: int|null, is_in_grace_period: bool, remaining_grace_days: int} */
    public function summary(TenantDatabase $tenant, string $mode = 'live'): array
    {
        $record = $this->subscriptions->currentForPurchase($tenant, $mode);

        return [
            'record' => $record,
            'scheduled' => $this->subscriptions->scheduled($tenant, $mode),
            'status' => $record ? $this->status($record) : 'No Subscription',
            'remaining_days' => $record?->end_date ? max(0, (int) ceil(now()->diffInDays($record->end_date, false))) : null,
            'is_in_grace_period' => $record && $this->status($record) === 'Grace Period',
            'remaining_grace_days' => $record ? $this->remainingGraceDays($record) : 0,
        ];
    }

    public function remainingGraceDays(UserPackage $record): int
    {
        return $this->status($record) === 'Grace Period'
            ? max(0, (int) ceil(now()->diffInDays($record->grace_expires_at, false))) : 0;
    }

    public function status(UserPackage $record): string
    {
        if (in_array($record->status, ['cancelled', 'canceled'], true)) {
            return 'Cancelled';
        }
        if ($record->start_date->isFuture()) {
            return 'Scheduled';
        }
        if (in_array($record->status, ['active', 'scheduled', 'grace_period'], true)) {
            if ($record->end_date !== null && $record->end_date->lte(now()) && $record->grace_expires_at?->isFuture()) {
                return 'Grace Period';
            }
            if ($record->end_date === null || $record->end_date->isFuture()) {
                return 'Active';
            }
        }

        return 'Expired';
    }

    /** Cosmetic lifecycle updates; access resolution always uses timestamps even before this job runs. */
    public function activateDue(): int
    {
        return UserPackage::query()
            ->where('status', 'scheduled')
            ->whereNotNull('package_payment_id')
            ->where('start_date', '<=', now())
            ->update(['status' => 'active']);
    }
}
