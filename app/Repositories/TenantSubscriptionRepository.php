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
            ->with(['package' => fn ($query) => $query->withTrashed()])
            ->where('start_date', '<=', now())->orderByDesc('start_date')->orderByDesc('id')->get();
    }
}
