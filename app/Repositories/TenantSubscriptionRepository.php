<?php

namespace App\Repositories;

use App\Models\TenantDatabase;
use App\Models\UserPackage;
use Illuminate\Database\Eloquent\Builder;
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
        return $this->currentCandidates($mode)->where('user_id', $tenant->user_id)
            ->where(fn(Builder $query) => $query->whereNull('tenant_database_id')->orWhere('tenant_database_id', $tenant->id))
            ->with(['package' => fn($query) => $query->withTrashed()])->first();
    }

    /** Shared entitlement precedence for checkout and read-only central reporting. */
    public function currentCandidates(string $mode): Builder
    {
        return UserPackage::query()
            ->where(fn(Builder $query) => $query->whereNull('mode')->orWhere('mode', $mode))
            ->where('start_date', '<=', now())
            ->orderByRaw("CASE
                WHEN status IN ('active', 'scheduled', 'grace_period') AND (end_date IS NULL OR end_date > ?) THEN 0
                WHEN status IN ('active', 'scheduled', 'grace_period') AND grace_expires_at > ? THEN 1
                ELSE 2 END", [now(), now()])
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }

    public function scheduled(TenantDatabase $tenant, string $mode): ?UserPackage
    {
        return $this->forTenant($tenant)->first(fn(UserPackage $record): bool => ($record->mode === null || $record->mode === $mode) && $record->package_payment_id !== null
            && in_array($record->status, ['active', 'scheduled'], true) && $record->start_date->isFuture());
    }
}
