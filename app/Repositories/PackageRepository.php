<?php

namespace App\Repositories;

use App\Models\Package;
use App\Services\PackagePricingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class PackageRepository
{
    public function __construct(private readonly PackagePricingService $pricing) {}

    /** @return Collection<int, Package> */
    public function catalog(): Collection
    {
        return Package::query()->where('status', 'active')->where('is_tenant_visible', true)
            ->with(['prices' => fn($query) => $query->where('is_active', true)->where('currency', config('subscriptions.currency')), 'limits'])
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Retrieve a paginated package list filtered by an optional search term.
     */
    public function paginate(int $perPage = 10, ?string $search = null, ?string $status = null): LengthAwarePaginator
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
            ->when($status !== null && $status !== '', fn(Builder $query) => $query->where('status', $status))
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
        return (new Package)->getConnection()->transaction(function () use ($attributes): Package {
            $package = Package::create(Arr::except($attributes, ['subscription_prices', 'subscription_currency', 'package_limits']));
            $this->pricing->save($package, $attributes);

            return $package;
        });
    }

    /**
     * Persist changes to an existing package.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Package $package, array $attributes): Package
    {
        return $package->getConnection()->transaction(function () use ($package, $attributes): Package {
            $package = Package::query()->lockForUpdate()->findOrFail($package->id);
            $package->update(Arr::except($attributes, ['subscription_prices', 'subscription_currency', 'package_limits']));
            $this->pricing->save($package, $attributes);

            return $package->refresh();
        });
    }
}
