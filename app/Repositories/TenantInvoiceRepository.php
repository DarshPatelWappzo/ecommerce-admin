<?php

namespace App\Repositories;

use App\Models\Tenant\Invoice;
use App\Models\Tenant\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TenantInvoiceRepository
{
    public function find(int $id): Invoice
    {
        return Invoice::with(['items', 'order:id,order_number,order_date,status,payment_status,invoice_error', 'order.payments'])->findOrFail($id);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Invoice::query()->with('order:id,order_number,payment_status,status');
        foreach (['status', 'mode', 'order_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['number', 'customer_name'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereLike($field, '%' . $filters[$field] . '%');
            }
        }
        if (! empty($filters['search'])) {
            $query->where(fn(Builder $q) => $q->whereLike('number', '%' . $filters['search'] . '%')->orWhereLike('customer_name', '%' . $filters['search'] . '%')->orWhereHas('order', fn(Builder $q) => $q->whereLike('order_number', '%' . $filters['search'] . '%')));
        }
        foreach (['from' => '>=', 'to' => '<='] as $field => $operator) {
            if (! empty($filters[$field])) {
                $query->whereDate('invoice_date', $operator, $filters[$field]);
            }
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    /** Orders with stable snapshots may be prepared before approval or prepaid collection. */
    public function candidates(?string $search): LengthAwarePaginator
    {
        return Order::whereIn('status', ['pending', 'confirmed', 'processing'])
            ->whereDoesntHave('invoice')
            ->when($search, fn(Builder $q) => $q->where(fn(Builder $q) => $q->whereLike('order_number', '%' . $search . '%')->orWhereLike('customer_name', '%' . $search . '%')))
            ->latest('id')->paginate(20)->withQueryString();
    }
}
