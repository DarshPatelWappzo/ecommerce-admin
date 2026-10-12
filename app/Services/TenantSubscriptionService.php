<?php

namespace App\Services;

use App\Models\TenantDatabase;
use App\Models\UserPackage;
use App\Repositories\TenantSubscriptionRepository;

class TenantSubscriptionService
{
    public function __construct(private readonly TenantSubscriptionRepository $subscriptions) {}

    /** @return array{record: UserPackage|null, status: string, remaining_days: int|null} */
    public function summary(TenantDatabase $tenant): array
    {
        $records = $this->subscriptions->forTenant($tenant);
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
            if ($record->end_date?->isPast() && $record->grace_expires_at?->isFuture()) {
                return 'Grace Period';
            }
            if ($record->status === 'active' && ($record->end_date === null || $record->end_date->isFuture())) {
                return 'Active';
            }
        }

        return 'Expired';
    }
}
