<?php

namespace App\Services;

use App\Models\Tenant\Coupon;
use App\Models\Tenant\CouponRedemption;
use App\Models\Tenant\Order;
use Brick\Math\RoundingMode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantCouponService
{
    /** Save restrictions under the same coupon lock used by order submission. */
    public function save(array $input, ?Coupon $coupon = null): Coupon
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($input, $coupon): Coupon {
                $coupon = $coupon?->exists ? Coupon::query()->lockForUpdate()->findOrFail($coupon->id) : new Coupon;
                foreach (['starts_at', 'ends_at'] as $field) {
                    if (isset($input[$field])) {
                        $input[$field] = Carbon::parse($input[$field])->setTimezone(config('app.timezone'));
                    }
                }
                $coupon->fill(Arr::except($input, ['products', 'categories', 'customers']))->save();
                foreach (['products', 'categories', 'customers'] as $relation) {
                    $coupon->{$relation}()->sync($input[$relation] ?? []);
                }

                return $coupon->load(['products', 'categories', 'customers']);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['code' => 'The coupon code has already been taken.']);
        }
    }

    /** Serialize status changes and soft deletion against redemption. */
    public function change(Coupon $coupon, ?bool $active): void
    {
        DB::connection('tenant')->transaction(function () use ($coupon, $active): void {
            $coupon = Coupon::query()->lockForUpdate()->findOrFail($coupon->id);
            if ($active === null) {
                $coupon->delete();
            } else {
                $coupon->update(['is_active' => $active]);
            }
        }, 3);
    }

    /**
     * Apply one coupon after catalog pricing and manual line discounts, before tax.
     * Product and category selections form a union; customer restrictions are additional.
     * Minimum subtotal uses only eligible net merchandise, excluding shipping and tax.
     *
     * @param  array<string, mixed>  $input  Validated order input.
     * @param  array<int, array<string, mixed>>  $items  Priced order lines.
     * @param  array<int, array<int, int>>  $categories  Product category IDs keyed by product.
     * @return array{items: array, coupon: array}
     */
    public function calculate(array $input, array $items, array $categories, bool $lock = false): array
    {
        $empty = ['coupon_id' => null, 'coupon_code' => null, 'coupon_discount' => '0.00', 'coupon_snapshot' => null];
        foreach ($items as &$item) {
            $item['coupon_discount'] = '0.00';
        }
        unset($item);
        if (trim($input['coupon_code'] ?? '') === '') {
            return ['items' => $items, 'coupon' => $empty];
        }
        $coupon = Coupon::query()->where('code', strtoupper(trim($input['coupon_code'])))
            ->when($lock, fn($query) => $query->lockForUpdate())->first();
        if (! $coupon || $coupon->status !== 'active') {
            $this->reject('This coupon is unavailable, scheduled or expired.');
        }
        $coupon->load([
            'products' => fn($query) => $query->when($lock, fn($query) => $query->lockForUpdate()),
            'categories' => fn($query) => $query->when($lock, fn($query) => $query->lockForUpdate()),
            'customers' => fn($query) => $query->when($lock, fn($query) => $query->lockForUpdate()),
        ]);
        $customerId = isset($input['customer_id']) ? (int) $input['customer_id'] : null;
        if (! $customerId && ($coupon->per_customer_limit !== null || $coupon->customers->isNotEmpty())) {
            $this->reject('Select a registered customer to use this coupon.');
        }
        if ($coupon->customers->isNotEmpty() && ! $coupon->customers->contains('id', $customerId)) {
            $this->reject('This coupon is not available for the selected customer.');
        }
        $usage = $coupon->redemptions()->whereNull('released_at')
            ->when($lock, fn($query) => $query->lockForUpdate());
        if ($coupon->usage_limit !== null && (clone $usage)->count() >= $coupon->usage_limit) {
            $this->reject('This coupon has reached its usage limit.');
        }
        if ($coupon->per_customer_limit !== null && (clone $usage)->where('customer_id', $customerId)->count() >= $coupon->per_customer_limit) {
            $this->reject('This customer has reached the coupon usage limit.');
        }
        $eligible = [];
        $subtotal = TenantOrderCalculationService::money('0');
        foreach ($items as $index => $item) {
            if (($coupon->products->isEmpty() && $coupon->categories->isEmpty())
                || $coupon->products->contains('id', $item['product_id'])
                || array_intersect($coupon->categories->modelKeys(), $categories[$item['product_id']] ?? [])
            ) {
                $amount = TenantOrderCalculationService::money($item['taxable_amount']);
                if ($amount->isGreaterThan('0')) {
                    $eligible[$index] = $amount;
                    $subtotal = $subtotal->plus($amount);
                }
            }
        }
        if ($subtotal->isZero() || $subtotal->isLessThan($coupon->minimum_subtotal)) {
            $this->reject('Eligible merchandise does not meet this coupon minimum subtotal.');
        }
        $discount = $coupon->discount_type === 'percentage'
            ? $subtotal->multipliedBy($coupon->discount_value)->dividedBy('100', 2, RoundingMode::HalfUp)
            : TenantOrderCalculationService::money($coupon->discount_value);
        if ($coupon->maximum_discount !== null && $discount->isGreaterThan($coupon->maximum_discount)) {
            $discount = TenantOrderCalculationService::money($coupon->maximum_discount);
        }
        if ($discount->isGreaterThan($subtotal)) {
            $discount = $subtotal;
        }
        $remaining = $discount;
        $remainingSubtotal = $subtotal;
        foreach ($eligible as $index => $amount) {
            $share = $remaining->multipliedBy($amount)->dividedBy($remainingSubtotal, 2, RoundingMode::Down);
            $items[$index]['coupon_discount'] = (string) $share;
            $items[$index]['discount_amount'] = (string) TenantOrderCalculationService::money($items[$index]['discount_amount'])->plus($share);
            $net = $amount->minus($share);
            $tax = $net->multipliedBy($items[$index]['tax_rate'])->dividedBy('100', 2, RoundingMode::HalfUp);
            $items[$index]['taxable_amount'] = (string) $net;
            $items[$index]['tax_amount'] = (string) $tax;
            $items[$index]['total_amount'] = (string) $net->plus($tax);
            $remaining = $remaining->minus($share);
            $remainingSubtotal = $remainingSubtotal->minus($amount);
        }

        return ['items' => $items, 'coupon' => [
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'coupon_discount' => (string) $discount,
            'coupon_snapshot' => [
                ...$coupon->only(['discount_type', 'discount_value', 'maximum_discount', 'minimum_subtotal']),
                'eligible_subtotal' => (string) $subtotal,
                'calculation_order' => 'catalog_then_manual_discount_then_coupon_then_tax',
                'product_ids' => $coupon->products->modelKeys(),
                'category_ids' => $coupon->categories->modelKeys(),
                'allocations' => array_map(fn(array $item): array => Arr::only($item, ['product_variant_id', 'coupon_discount']), $items),
            ],
        ]];
    }

    /**
     * Drafts do not consume usage. Pending through delivered orders consume one redemption.
     * Called inside the order transaction after a locked eligibility recalculation.
     */
    public function redeem(Order $order): void
    {
        if ($order->coupon_id !== null) {
            CouponRedemption::query()->firstOrCreate(['order_id' => $order->id], ['coupon_id' => $order->coupon_id, 'customer_id' => $order->customer_id]);
        }
    }

    /** Release usage only after the existing order flow permits cancellation. */
    public function release(Order $order): void
    {
        if ($order->coupon_id !== null) {
            Coupon::withTrashed()->whereKey($order->coupon_id)->lockForUpdate()->firstOrFail();
            CouponRedemption::query()->where('order_id', $order->id)->whereNull('released_at')->update(['released_at' => now()]);
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['coupon_code' => $message]);
    }
}
