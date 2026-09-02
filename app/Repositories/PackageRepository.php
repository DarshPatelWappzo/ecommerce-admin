<?php

namespace App\Repositories;

use App\Models\Package;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class PackageRepository
{
    /**
     * Retrieve a paginated package list filtered by an optional search term.
     */
    public function paginate(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return Package::query()
            ->when($search !== null && $search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('min_monthly_cost', 'like', "%{$search}%")
                        ->orWhere('max_monthly_cost', 'like', "%{$search}%")
                        ->orWhere('infrastructure_summary', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Persist a new package.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Package
    {
        return Package::create($attributes);
    }

    /**
     * Persist changes to an existing package.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Package $package, array $attributes): Package
    {
        $package->update($attributes);

        return $package->refresh();
    }
}
