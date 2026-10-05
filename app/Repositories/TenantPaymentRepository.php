<?php

namespace App\Repositories;

use App\Models\Tenant\OrderPayment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TenantPaymentRepository
{
    public function find(int $id): OrderPayment
    {
        return OrderPayment::with(['order.paymentCheckout.events', 'recorder'])->findOrFail($id);
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = OrderPayment::with('order:id,order_number,customer_name,customer_email,payment_status');
        if (! empty($filters['search'])) {
            $query->whereHas('order', fn(Builder $orders) => $orders
                ->whereLike('order_number', '%' . $filters['search'] . '%')
                ->orWhereLike('customer_name', '%' . $filters['search'] . '%')
                ->orWhereLike('customer_email', '%' . $filters['search'] . '%'));
        }
        foreach (['method', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString();
    }
}
