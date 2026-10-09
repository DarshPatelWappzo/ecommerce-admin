<?php

namespace App\Services;

use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use App\Models\Tenant\ProductVariant;
use App\Repositories\CustomerShoppingRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CustomerCheckoutService
{
    public function __construct(
        private readonly CustomerShoppingRepository $shopping,
        private readonly CustomerCartService $carts,
        private readonly TenantOrderCalculationService $calculator,
        private readonly TenantOrderService $orders,
        private readonly TenantOrderIdempotencyService $idempotency,
    ) {}

    /** Resolve owned addresses into the immutable shape consumed by the existing order service. */
    private function address(Customer $customer, ?int $id, string $type): array
    {
        $address = $id
            ? $customer->addresses()->find($id)
            : $customer->addresses()->where('is_default_' . $type, true)->first();
        if (! $address || ! filled($address->phone)) {
            throw ValidationException::withMessages([$type . '_address_id' => 'Select your own saved ' . $type . ' address.']);
        }
        $data = [
            'name' => $address->recipient_name,
            'phone' => trim($address->phone_country_code . ' ' . $address->phone),
            ...$address->only(['address_line_1', 'address_line_2', 'city', 'state_code', 'country_code', 'postal_code']),
        ];
        Validator::make($data, [
            'name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:30'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postal_code' => ['required', 'string', 'max:20'],
            'country_code' => ['required', 'in:IN'],
            'state_code' => ['required', Rule::in(array_keys(config('customer_locations.IN.states')))],
        ])->validate();

        return $data;
    }

    private function input(Customer $customer, Cart $cart, array $input, ?string $coupon = null): array
    {
        $items = $cart->items()->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }
        $variants = ProductVariant::with('product.tax')->whereIn('id', $items->pluck('product_variant_id'))->get()->keyBy('id');
        foreach ($items as $item) {
            $variant = $variants->get($item->product_variant_id);
            if (! $variant || ! $variant->status || ! $variant->product?->status || $variant->product_id !== $item->product_id) {
                throw ValidationException::withMessages(['items' => 'A cart product or variant is unavailable.']);
            }
            $this->carts->stock($variant, $item->quantity);
        }

        return [
            'customer_id' => $customer->id,
            'currency' => 'INR',
            'items' => $items->map->only(['product_id', 'product_variant_id', 'quantity'])->all(),
            'coupon_code' => $coupon ?? $cart->coupon_code,
            'billing' => $this->address($customer, $input['billing_address_id'] ?? null, 'billing'),
            'shipping' => $this->address($customer, $input['shipping_address_id'] ?? null, 'shipping'),
            'shipping_amount' => config('customer_shopping.shipping_amount', '0.00'),
            'shipping_tax_id' => config('customer_shopping.shipping_tax_id'),
            'payment_method' => $input['payment_method'] ?? array_key_first(TenantPaymentService::methods()),
            'customer_note' => $input['customer_note'] ?? null,
        ];
    }

    public function summary(Customer $customer, array $input): array
    {
        return $this->shopping->transaction($customer, function () use ($customer, $input): array {
            $cart = $this->carts->active($customer);
            $payload = $this->input($customer, $cart, $input);

            return [
                ...$this->calculator->calculate($payload),
                'cart_id' => $cart->id,
                'addresses' => ['billing' => $payload['billing'], 'shipping' => $payload['shipping']],
                'payment_methods' => TenantPaymentService::methods()
            ];
        });
    }

    public function coupon(Customer $customer, array $input, bool $remove = false): array
    {
        return $this->shopping->transaction($customer, function () use ($customer, $input, $remove): array {
            $cart = $this->carts->active($customer);
            $code = $remove ? '' : strtoupper($input['coupon_code']);
            $payload = $this->input($customer, $cart, $input, $code);
            $calculation = $this->calculator->calculate($payload);
            $cart->update(['coupon_code' => $code === '' ? null : $code]);

            return $calculation;
        });
    }

    /** Persist order and durable cart association atomically; gateway calls happen after this commits. */
    public function place(Customer $customer, array $input): int
    {
        $result = $this->shopping->transaction($customer, fn(): array => $this->idempotency->run(
            $customer->id,
            'customer.checkout',
            $input['idempotency_key'],
            $input,
            function () use ($customer, $input): array {
                if (! array_key_exists($input['payment_method'], TenantPaymentService::methods())) {
                    throw ValidationException::withMessages(['payment_method' => 'This payment method is disabled.']);
                }
                $cart = $this->carts->active($customer);
                if ($cart->orders()->whereNull('converted_at')->whereHas('order', fn($query) => $query->where('status', '!=', 'cancelled'))->exists()) {
                    throw new ConflictHttpException('This cart already has an order awaiting payment. Resume that order instead.');
                }
                $payload = $this->input($customer, $cart, $input);
                $order = $this->orders->save([
                    ...$payload,
                    'submit_as' => 'confirmed',
                    'expected_pricing_fingerprint' => $input['expected_pricing_fingerprint'],
                ], $customer, 'customer');
                $cart->orders()->create([
                    'order_id' => $order->id,
                    'payment_method' => $input['payment_method'],
                    'items_snapshot' => $cart->items()->get()->map->only(['id', 'product_id', 'product_variant_id', 'quantity', 'revision'])->all(),
                ]);
                $this->carts->active($customer);

                return ['order_id' => $order->id];
            },
        ));

        return $result['order_id'];
    }
}
