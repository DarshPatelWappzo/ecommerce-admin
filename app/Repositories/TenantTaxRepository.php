<?php

namespace App\Repositories;

use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TenantTaxRepository
{
    /** @param array{search?: ?string, is_active?: bool|int|string|null} $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';

        return Tax::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('name', '%' . $search . '%')->orWhereLike('code', '%' . $search . '%');
                });
            })
            ->when(isset($filters['is_active']), fn(Builder $query) => $query->where('is_active', $filters['is_active']))
            ->latest('id')->paginate(10)->withQueryString();
    }

    /** @param array{name: string, code: string, rate: string|int|float, description?: ?string, is_active: bool|int|string} $data */
    public function save(array $data, ?Tax $tax = null): Tax
    {
        $tax ??= new Tax;
        $tax->fill($data)->save();

        return $tax;
    }
}
