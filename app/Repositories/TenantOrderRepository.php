<?php

namespace App\Repositories;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TenantOrderRepository
{
    public const RELATIONS = ['items', 'addresses', 'payments', 'shipment', 'histories'];

    /**
     * Fetch an order by identifier, optionally locking it for update.
     *
     * @param  int  $id  The order identifier.
     * @param  bool  $lock  Whether to lock the row for concurrent writes.
     * @return Order The matching order model.
     */
    public function find(int $id, bool $lock = false): Order
    {
        return Order::query()->when($lock, fn (Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    /**
     * Return a fully loaded order with its nested operational records.
     *
     * @param  int  $id  The order identifier.
     * @return Order The order with relationships loaded.
     */
    public function details(int $id): Order
    {
        return $this->find($id)->load(self::RELATIONS);
    }

    /**
     * List tenant orders with optional search, status, and date filters.
     *
     * @param  array  $filters  The validated order filter payload.
     * @return LengthAwarePaginator The paginated order collection.
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Order::query();
        if (! empty($filters['search'])) {
            $query->where(fn (Builder $query) => $query->whereLike('order_number', '%'.$filters['search'].'%')->orWhereLike('customer_name', '%'.$filters['search'].'%')->orWhereLike('customer_email', '%'.$filters['search'].'%'));
        }
        foreach (['status', 'payment_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', fn (Builder $query) => $query->where('method', $filters['payment_method']));
        }
        if (! empty($filters['from'])) {
            $query->where('order_date', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('order_date', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    /**
     * Load the requested product variants along with their product and option metadata.
     *
     * @param  array  $ids  The product variant identifiers.
     * @param  bool  $lock  Whether to lock the variant and parent product rows.
     * @param  bool  $trashed  Whether to include soft-deleted variants.
     * @return Collection The keyed collection of variant records.
     */
    public function variants(array $ids, bool $lock = false, bool $trashed = false): Collection
    {
        if ($lock) {
            Product::withTrashed()->whereIn('id', ProductVariant::withTrashed()->whereIn('id', $ids)->select('product_id'))->orderBy('id')->lockForUpdate()->get();
        }

        return ProductVariant::query()->when($trashed, fn (Builder $query) => $query->withTrashed())
            ->with(['product.tax', 'attributeValues.option'])->whereIn('id', $ids)->orderBy('id')
            ->when($lock, fn (Builder $query) => $query->lockForUpdate())->get()->keyBy('id');
    }

    /**
     * Load an active customer record for order assignment.
     *
     * @param  int  $id  The customer identifier.
     * @return Customer The active customer model.
     */
    public function customer(int $id): Customer
    {
        return Customer::where('status', 'active')->findOrFail($id);
    }

    /**
     * Fetch an active tax record by identifier.
     *
     * @param  int  $id  The tax identifier.
     * @return Tax|null The active tax model or null when absent.
     */
    public function tax(int $id): ?Tax
    {
        return Tax::where('is_active', true)->find($id);
    }

    /**
     * Retrieve the tax options available to the tenant order UI.
     *
     * @return Collection The active tax list.
     */
    public function taxes(): Collection
    {
        return Tax::where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate']);
    }

    /**
     * Return the select2-style lookup records for order forms.
     *
     * @param  array  $filters  The lookup kind and search term.
     * @return LengthAwarePaginator The paginated result set.
     */
    public function options(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        if (($filters['kind'] ?? '') === 'taxes') {
            return Tax::where('is_active', true)->where(fn (Builder $query) => $query->whereLike('name', '%'.$search.'%')->orWhereLike('code', '%'.$search.'%'))
                ->orderBy('name')->paginate(20, ['id', 'name', 'code', 'rate']);
        }
        if (($filters['kind'] ?? '') === 'customers') {
            return Customer::where('status', 'active')->with('addresses')
                ->where(fn (Builder $query) => $query->whereLike('first_name', '%'.$search.'%')->orWhereLike('last_name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')->orWhereLike('customer_code', '%'.$search.'%'))
                ->orderBy('first_name')->paginate(20);
        }

        return ProductVariant::with(['product.tax', 'attributeValues.option'])->where('status', true)
            ->whereHas('product', fn (Builder $query) => $query->where('status', true))
            ->where(fn (Builder $query) => $query->whereLike('sku', '%'.$search.'%')->orWhereHas('product', fn (Builder $query) => $query->whereLike('name', '%'.$search.'%')))
            ->orderBy('id')->paginate(20);
    }

    /**
     * Persist the order attributes for an existing model instance.
     *
     * @param  Order  $order  The order model.
     * @param  array  $data  The order fields to update.
     * @return Order The saved order model.
     */
    public function save(Order $order, array $data): Order
    {
        $order->fill($data)->save();

        return $order;
    }

    /**
     * Replace the order items and addresses with a fresh set from the current draft calculation.
     *
     * @param  Order  $order  The order to update.
     * @param  array  $items  The order line item rows.
     * @param  array  $addresses  The billing and shipping address rows.
     */
    public function replaceDetails(Order $order, array $items, array $addresses): void
    {
        $order->items()->delete();
        $order->addresses()->delete();
        $order->items()->createMany($items);
        $order->addresses()->createMany($addresses);
    }

    /**
     * Adjust inventory and reserve counts while recording a stock movement audit entry.
     *
     * @param  Order  $order  The associated order.
     * @param  ProductVariant  $variant  The variant whose stock is changing.
     * @param  string  $event  The stock movement event name.
     * @param  int  $quantityDelta  The on-hand quantity delta.
     * @param  int  $reservedDelta  The reserved quantity delta.
     */
    public function stock(Order $order, ProductVariant $variant, string $event, int $quantityDelta, int $reservedDelta): void
    {
        $variant->quantity += $quantityDelta;
        $variant->reserved_quantity += $reservedDelta;
        $variant->save();
        DB::connection('tenant')->table('order_stock_movements')->insert([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
            'event' => $event,
            'quantity_delta' => $quantityDelta,
            'reserved_delta' => $reservedDelta,
            'quantity_after' => $variant->quantity,
            'reserved_after' => $variant->reserved_quantity,
            'created_at' => now(),
        ]);
    }
}
