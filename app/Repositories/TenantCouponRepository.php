<?php

namespace App\Repositories;

use App\Models\Tenant\Category;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantCouponRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Coupon::query()->withCount(['redemptions as usage_count' => fn($query) => $query->whereNull('released_at')]);
        if (! empty($filters['search'])) {
            $query->where(fn($query) => $query->whereLike('code', '%' . $filters['search'] . '%')->orWhereLike('description', '%' . $filters['search'] . '%'));
        }
        if (! empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }
        $status = $filters['status'] ?? null;
        if ($status === 'disabled') {
            $query->where('is_active', false);
        } elseif ($status) {
            $query->where('is_active', true);
            if ($status === 'scheduled') {
                $query->where('starts_at', '>', now());
            } elseif ($status === 'expired') {
                $query->where('ends_at', '<', now());
            } else {
                $query->where(fn($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
            }
        }
        if (! empty($filters['from'])) {
            $query->where(fn($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $filters['from'] . ' 00:00:00'));
        }
        if (! empty($filters['to'])) {
            $query->where(fn($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $filters['to'] . ' 23:59:59'));
        }

        return $query->latest('id')->paginate(15)->withQueryString();
    }

    public function choices(): array
    {
        return [
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'customer_code']),
        ];
    }
}
