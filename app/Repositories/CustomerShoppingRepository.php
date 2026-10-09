<?php

namespace App\Repositories;

use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Wishlist;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerShoppingRepository
{
    public const PRODUCT_RELATIONS = ['tax', 'images', 'variants.attributeValues.option', 'variants.images', 'categories'];

    /** Serialize mutations, including first-cart creation, on the tenant customer. */
    public function transaction(Customer $customer, Closure $action): mixed
    {
        return DB::connection('tenant')->transaction(function () use ($customer, $action): mixed {
            Customer::withTrashed()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            return $action();
        }, 3);
    }

    public function cart(Customer $customer): Cart
    {
        return Cart::firstOrCreate(['active_customer_id' => $customer->id], ['customer_id' => $customer->id]);
    }

    public function products(): Builder
    {
        return Product::with(self::PRODUCT_RELATIONS)->where('status', true);
    }

    public function wishlist(Customer $customer): Builder
    {
        return Wishlist::where('customer_id', $customer->id)->with(array_map(fn(string $relation): string => 'product.' . $relation, self::PRODUCT_RELATIONS));
    }

    public function orders(Customer $customer): Builder
    {
        return Order::where('customer_id', $customer->id)->where('status', '!=', 'draft');
    }

    public function order(Customer $customer, int $id): Order
    {
        return $this->orders($customer)->with(['items.product', 'items.variant', 'items.returns', 'items.replacements', 'returns.refund', 'addresses', 'payments', 'shipment', 'invoice', 'paymentCheckout'])->findOrFail($id);
    }

    public function variant(int $productId, ?int $variantId): ProductVariant
    {
        $product = $this->products()->find($productId);
        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'This product is unavailable.']);
        }
        if ($variantId === null && $product->product_type !== 'simple') {
            throw ValidationException::withMessages(['product_variant_id' => 'Select a valid product variant.']);
        }
        $variants = $product->variants->where('status', true);
        $variant = $variantId === null && $variants->count() === 1 ? $variants->first() : $variants->firstWhere('id', $variantId);
        if (! $variant) {
            throw ValidationException::withMessages(['product_variant_id' => 'Select an active variant belonging to this product.']);
        }
        $variant->setRelation('product', $product);

        return $variant;
    }
}
