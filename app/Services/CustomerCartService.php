<?php

namespace App\Services;

use App\Models\Tenant\Cart;
use App\Models\Tenant\CartOrder;
use App\Models\Tenant\Customer;
use App\Models\Tenant\ProductVariant;
use App\Repositories\CustomerShoppingRepository;
use Illuminate\Validation\ValidationException;

class CustomerCartService
{
    public function __construct(private readonly CustomerShoppingRepository $shopping, private readonly TenantOrderCalculationService $calculator) {}

    /** Return the active cart after completing any verified purchase. Caller holds the customer lock. */
    public function active(Customer $customer): Cart
    {
        $cart = $this->shopping->cart($customer);
        $links = $cart->orders()->whereNull('converted_at')->with('order.paymentCheckout')->get();
        foreach ($links as $link) {
            $order = $link->order;
            if ($order->status === 'cancelled' || $order->paymentCheckout?->status === 'review') {
                continue;
            }
            if ($order->payment_status !== 'paid' && ! ($link->payment_method === 'cod' && in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered'], true))) {
                continue;
            }
            $snapshot = collect($link->items_snapshot)->keyBy('id');
            $remaining = $cart->items()->get()->filter(fn($line): bool => ! isset($snapshot[$line->id]) || $snapshot[$line->id]['revision'] !== $line->revision);
            $cart->update(['status' => 'converted', 'active_customer_id' => null]);
            $next = $this->shopping->cart($customer);
            foreach ($remaining as $line) {
                $line->update(['cart_id' => $next->id]);
            }
            $link->update(['converted_at' => now()]);
            $cart = $next;
        }

        return $cart;
    }

    public function view(Customer $customer): array
    {
        return $this->shopping->transaction($customer, fn(): array => $this->describe($this->active($customer)));
    }

    /** Complete an associated shopping cart after payment commits, including webhook and staff receipts. */
    public function completeOrder(int $orderId): void
    {
        $link = CartOrder::where('order_id', $orderId)->whereNull('converted_at')->first();
        if (! $link) {
            return;
        }
        $cart = Cart::findOrFail($link->cart_id);
        $customer = Customer::withTrashed()->findOrFail($cart->customer_id);
        $this->shopping->transaction($customer, fn(): Cart => $this->active($customer));
    }

    public function stock(ProductVariant $variant, int $quantity): void
    {
        if ($quantity < 1 || $quantity > 100000) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 100000.']);
        }
        if (! $variant->product->tax?->is_available) {
            throw ValidationException::withMessages(['product_id' => 'This product does not have an available tax configuration.']);
        }
        if ($quantity > $variant->quantity - $variant->reserved_quantity) {
            throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds available stock.']);
        }
    }

    /** Caller holds the customer lock; no inventory is reserved here. */
    public function add(Customer $customer, array $input): void
    {
        $cart = $this->active($customer);
        $variant = $this->shopping->variant((int) $input['product_id'], isset($input['product_variant_id']) ? (int) $input['product_variant_id'] : null);
        $line = $cart->items()->where('product_variant_id', $variant->id)->first();
        if (! $line && $cart->items()->count() >= 100) {
            throw ValidationException::withMessages(['items' => 'A cart may contain at most 100 items.']);
        }
        $quantity = ($line?->quantity ?? 0) + (int) $input['quantity'];
        $this->stock($variant, $quantity);
        $cart->items()->updateOrCreate(['product_variant_id' => $variant->id], [
            'product_id' => $variant->product_id,
            'quantity' => $quantity,
            'revision' => ($line?->revision ?? 0) + 1,
        ]);
    }

    public function mutate(Customer $customer, string $action, array $input = [], ?int $id = null): array
    {
        return $this->shopping->transaction($customer, function () use ($customer, $action, $input, $id): array {
            $cart = $this->active($customer);
            if ($action === 'add') {
                $this->add($customer, $input);
            } elseif ($action === 'clear') {
                $cart->items()->delete();
                $cart->update(['coupon_code' => null]);
            } else {
                $line = $cart->items()->findOrFail($id);
                if ($action === 'remove') {
                    $line->delete();
                } else {
                    $quantity = match ($input['operation'] ?? 'set') {
                        'increase' => $line->quantity + (int) $input['quantity'],
                        'decrease' => $line->quantity - (int) $input['quantity'],
                        default => (int) $input['quantity'],
                    };
                    $variant = $this->shopping->variant($line->product_id, $line->product_variant_id);
                    $this->stock($variant, $quantity);
                    $line->update(['quantity' => $quantity, 'revision' => $line->revision + 1]);
                }
            }

            return $this->describe($cart->refresh());
        });
    }

    public function describe(Cart $cart): array
    {
        $cart->load(['items.product.images', 'items.product.tax', 'items.variant.images', 'items.variant.attributeValues.option']);
        $items = [];
        foreach ($cart->items as $line) {
            $message = null;
            $pricing = null;
            try {
                $variant = $line->variant;
                if (! $variant || $variant->trashed() || ! $variant->status || ! $line->product || $line->product->trashed() || ! $line->product->status) {
                    throw ValidationException::withMessages(['product' => 'This cart item is unavailable.']);
                }
                $variant->setRelation('product', $line->product);
                $this->stock($variant, $line->quantity);
            } catch (ValidationException $exception) {
                $message = $exception->validator->errors()->first();
            }
            $items[] = [
                ...$line->only(['id', 'product_id', 'product_variant_id', 'quantity']),
                'name' => $line->product?->name,
                'sku' => $line->variant?->sku,
                'image' => $line->variant?->images->first()?->url ?? $line->product?->images->first()?->url,
                'variant' => $line->variant?->attributeValues->map(fn($value) => $value->option?->label)->filter()->implode(' / '),
                'current_price' => $line->variant ? $this->calculator->catalogPrice($line->variant) : null,
                'available_stock' => max(0, ($line->variant?->quantity ?? 0) - ($line->variant?->reserved_quantity ?? 0)),
                'valid' => $message === null,
                'validation_message' => $message,
                'pricing' => $pricing,
            ];
        }
        $totals = null;
        $couponError = null;
        $validLines = $cart->items->filter(fn($line): bool => collect($items)->firstWhere('id', $line->id)['valid'])->map->only(['product_id', 'product_variant_id', 'quantity'])->values()->all();
        $pricing = $validLines === [] ? [] : $this->calculator->calculate(['items' => $validLines])['items'];
        if ($items !== [] && collect($items)->every('valid', true)) {
            try {
                $calculation = $this->calculator->calculate(['customer_id' => $cart->customer_id, 'coupon_code' => $cart->coupon_code, 'items' => $validLines]);
                $totals = $calculation['totals'];
                $pricing = $calculation['items'];
            } catch (ValidationException $exception) {
                $couponError = $exception->validator->errors()->first();
            }
        }

        $pricing = collect($pricing)->keyBy('product_variant_id');
        $items = array_map(fn(array $item): array => [...$item, 'pricing' => $item['valid'] ? $pricing->get($item['product_variant_id']) : null], $items);

        return [
            'id' => $cart->id,
            'status' => $cart->status,
            'coupon_code' => $cart->coupon_code,
            'coupon_error' => $couponError,
            'items' => $items,
            'totals' => $totals,
            'pending_order_id' => $cart->orders()->whereNull('converted_at')->whereHas('order', fn($query) => $query->where('status', '!=', 'cancelled'))->value('order_id')
        ];
    }
}
