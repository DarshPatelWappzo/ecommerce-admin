<?php

namespace App\Repositories;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\ReplacementRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TenantReplacementRepository
{
    public const RELATIONS = ['order', 'item', 'reason', 'shipment', 'histories', 'returnRequest.refund'];

    public function details(int $id): ReplacementRequest
    {
        return ReplacementRequest::with(self::RELATIONS)->findOrFail($id);
    }

    public function deliveredOrder(string $number): ?Order
    {
        return Order::with('items')->where('order_number', $number)->where('status', 'delivered')->whereNotNull('customer_id')->first();
    }

    /** @return Collection<int, string> Tenant customers with replacement history, keyed by ID. */
    public function customerFilterOptions(): Collection
    {
        return Customer::withTrashed()
            ->whereIn('id', ReplacementRequest::query()->select('customer_id'))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->get(['id', 'first_name', 'last_name', 'customer_code', 'deleted_at'])
            ->mapWithKeys(fn(Customer $customer): array => [
                $customer->id => trim($customer->first_name . ' ' . $customer->last_name) . ' (' . $customer->customer_code . ')' . ($customer->trashed() ? ' — Archived' : ''),
            ]);
    }

    /** @param array<string, mixed> $filters Validated list filters. */
    public function paginate(array $filters, ?int $customerId = null): LengthAwarePaginator
    {
        $query = ReplacementRequest::with(self::RELATIONS)->when($customerId !== null, fn($query) => $query->where('customer_id', $customerId));
        foreach (['status', 'customer_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['search'])) {
            $query->where('replacement_number', 'like', '%' . $filters['search'] . '%');
        }
        if (! empty($filters['order_number'])) {
            $query->whereHas('order', fn($query) => $query->where('order_number', 'like', '%' . $filters['order_number'] . '%'));
        }
        if (! empty($filters['from'])) {
            $query->where('requested_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('requested_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }
}
