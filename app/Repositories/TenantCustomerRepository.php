<?php

namespace App\Repositories;

use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TenantCustomerRepository
{
    public function find(int $id, bool $lock = false): Customer
    {
        return Customer::query()->when($lock, fn(Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    public function details(int $id): Customer
    {
        return $this->find($id)->load('addresses');
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Customer::query();
        if (! empty($filters['search'])) {
            $query->where(function (Builder $query) use ($filters): void {
                foreach (['customer_code', 'first_name', 'last_name', 'email', 'phone'] as $field) {
                    $query->orWhereLike($field, '%' . $filters['search'] . '%');
                }
                $query->orWhere(function (Builder $nameQuery) use ($filters): void {
                    foreach (preg_split('/\s+/', trim($filters['search'])) as $part) {
                        $nameQuery->where(fn(Builder $partQuery) => $partQuery->whereLike('first_name', '%' . $part . '%')->orWhereLike('last_name', '%' . $part . '%'));
                    }
                });
                $phone = preg_replace('/[\s()+.-]+/', '', $filters['search']);
                if ($phone !== '' && ctype_digit($phone)) {
                    $query->orWhereLike('phone', '%' . $phone . '%');
                }
            });
        }
        foreach (['status', 'customer_type'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['joined_from'])) {
            $query->where('created_at', '>=', $filters['joined_from'] . ' 00:00:00');
        }
        if (! empty($filters['joined_to'])) {
            $query->where('created_at', '<', Carbon::parse($filters['joined_to'])->addDay()->startOfDay());
        }

        return $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function save(Customer $customer, array $data): Customer
    {
        $customer->fill($data)->save();

        return $customer;
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }
}
