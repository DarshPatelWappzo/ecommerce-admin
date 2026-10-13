<?php

namespace App\Repositories;

use App\Models\TenantDatabase;
use App\Models\UserPackage;
use Illuminate\Database\Eloquent\Collection;

class TenantSubscriptionRepository
{
    /** @return Collection<int, UserPackage> Central owner assignments, never tenant staff IDs. */
    public function forTenant(TenantDatabase $tenant): Collection
    {
        return UserPackage::query()->where('user_id', $tenant->user_id)
            ->with(['package' => fn($query) => $query->withTrashed()])
            ->where(fn($query) => $query->whereNull('tenant_database_id')->orWhere('tenant_database_id', $tenant->id))
            ->where(fn($query) => $query->where('start_date', '<=', now())->orWhereNotNull('package_payment_id'))
            ->orderByDesc('start_date')->orderByDesc('id')->get();
    }

    public function currentForPurchase(TenantDatabase $tenant, string $mode): ?UserPackage
    {
        $records = $this->forTenant($tenant)->filter(fn(UserPackage $record): bool => ($record->mode === null || $record->mode === $mode) && $record->start_date->lte(now()));
        $active = $records->first(fn(UserPackage $record): bool => in_array($record->status, ['active', 'scheduled', 'grace_period'], true)
            && ($record->end_date === null || $record->end_date->isFuture()));

        return $active ?? $records->first(fn(UserPackage $record): bool => in_array($record->status, ['active', 'scheduled', 'grace_period'], true)
            && $record->grace_expires_at?->isFuture()) ?? $records->first();
    }

    public function scheduled(TenantDatabase $tenant, string $mode): ?UserPackage
    {
        return $this->forTenant($tenant)->first(fn(UserPackage $record): bool => ($record->mode === null || $record->mode === $mode) && $record->package_payment_id !== null
            && in_array($record->status, ['active', 'scheduled'], true) && $record->start_date->isFuture());
    }
}
