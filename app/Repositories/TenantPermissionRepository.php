<?php

namespace App\Repositories;

use App\Models\Tenant\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TenantPermissionRepository
{
    /**
     * Retrieve tenant permissions grouped by their module slug.
     *
     * @return array<string, Collection<int, Permission>>
     */
    public function allGroupedByModule(): array
    {
        return Permission::query()
            ->orderBy('slug')
            ->get()
            ->groupBy(fn (Permission $permission): string => Str::before($permission->slug, '.'))
            ->all();
    }
}
