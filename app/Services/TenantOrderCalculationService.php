<?php

namespace App\Services;

use App\Models\Tenant\ProductVariant;
use App\Repositories\TenantOrderRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class TenantOrderCalculationService
{
    public function __construct(private readonly TenantOrderRepository $orders, private readonly TenantCouponService $coupons) {}

    /**
     * Convert a value into a two-decimal BigDecimal for order accounting.
     *
     * @param  mixed  $value  The raw numeric value.
     * @return BigDecimal The rounded monetary value.
     */
    public static function money(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    /**
     * Resolve the current effective product price after checking active special pricing rules.
     *
     * @param  ProductVariant  $variant  The product variant to price.
     * @return string The effective unit price as a string.
     */
    public function catalogPrice(ProductVariant $variant): string
    {
        if ($variant->special_price !== null && (! $variant->special_price_from || $variant->special_price_from->lte(today())) && (! $variant->special_price_to || $variant->special_price_to->gte(today()))) {
            return $variant->special_price;
        }

        return $variant->price;
    }

    /**
     * Calculate per-item subtotals, taxes, shipping, and the order fingerprint from raw input.
     *
     * @param  array  $input  The order payload containing items and optional shipping data.
     * @param  bool  $lock  Whether to lock inventory rows during validation.
     * @return array The item list, totals, and price fingerprint.
     */
    public function calculate(array $input, bool $lock = false): array
    {
        $variants = $this->orders->variants(array_column($input['items'], 'product_variant_id'), $lock);
        $items = [];
        $subtotal = self::money('0');
        $discountTotal = self::money('0');
        $taxTotal = self::money('0');
        foreach ($input['items'] as $index => $line) {
            $variant = $variants->get($line['product_variant_id']);
            $product = $variant?->product;
            if (! $variant || ! $variant->status || ! $product?->status || $product->id !== (int) $line['product_id']) {
                throw ValidationException::withMessages(['items.' . $index . '.product_variant_id' => 'Select an active variant belonging to the selected product.']);
            }
            $tax = $product->tax;
            if (! $tax?->is_available) {
                throw ValidationException::withMessages(['items.' . $index . '.product_id' => 'Configure an active product tax before adding this item to an order. An explicit zero-rate tax is allowed.']);
            }
            $price = $this->catalogPrice($variant);
            $overridden = isset($line['unit_price']);
            $unit = self::money($overridden ? $line['unit_price'] : $price);
            $base = $unit->multipliedBy($line['quantity']);
            $discount = self::money($line['discount_amount'] ?? '0');
            if ($discount->isGreaterThan($base)) {
                throw ValidationException::withMessages(['items.' . $index . '.discount_amount' => 'Item discount cannot exceed its subtotal.']);
            }
            $taxable = $base->minus($discount);
            $amount = $taxable->multipliedBy($tax->rate)->dividedBy('100', 2, RoundingMode::HalfUp);
            $total = $taxable->plus($amount);
            foreach ([$base, $total] as $value) {
                $this->checkLimit($value);
            }
            $items[] = [
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'product_name' => $product->name,
                'variant_name' => $variant->attributeValues->map(fn($value) => $value->option?->label)->filter()->implode(' / ') ?: null,
                'sku' => $variant->sku,
                'hsn_code' => $product->hsn_code,
                'quantity' => (int) $line['quantity'],
                'price_overridden' => $overridden,
                'unit_price' => (string) $unit,
                'subtotal' => (string) $base,
                'discount_amount' => (string) $discount,
                'taxable_amount' => (string) $taxable,
                'tax_rate' => $tax->rate,
                'tax_name' => $tax->name,
                'tax_code' => $tax->code,
                'cgst_amount' => null,
                'sgst_amount' => null,
                'igst_amount' => null,
                'tax_amount' => (string) $amount,
                'total_amount' => (string) $total,
            ];
            $subtotal = $subtotal->plus($base);
            $discountTotal = $discountTotal->plus($discount);
            $taxTotal = $taxTotal->plus($amount);
        }
        $categories = $variants->mapWithKeys(fn($variant): array => [$variant->product_id => $variant->product->categories->modelKeys()])->all();
        $coupon = $this->coupons->calculate($input, $items, $categories, $lock);
        $items = $coupon['items'];
        $discountTotal = self::money('0');
        $taxTotal = self::money('0');
        foreach ($items as $item) {
            $discountTotal = $discountTotal->plus($item['discount_amount']);
            $taxTotal = $taxTotal->plus($item['tax_amount']);
        }
        $shipping = self::money($input['shipping_amount'] ?? '0');
        $shippingTax = isset($input['shipping_tax_id']) ? $this->orders->tax((int) $input['shipping_tax_id']) : null;
        if (isset($input['shipping_tax_id']) && ! $shippingTax) {
            throw ValidationException::withMessages(['shipping_tax_id' => 'Select an active tax for the shipping charge.']);
        }
        $shippingTaxAmount = $shippingTax ? $shipping->multipliedBy($shippingTax->rate)->dividedBy('100', 2, RoundingMode::HalfUp) : self::money('0');
        $taxTotal = $taxTotal->plus($shippingTaxAmount);
        $grand = $subtotal->minus($discountTotal)->plus($shipping)->plus($taxTotal);
        $this->checkLimit($subtotal);
        $this->checkLimit($grand);
        $totals = [
            ...$coupon['coupon'],
            'subtotal' => (string) $subtotal,
            'discount_total' => (string) $discountTotal,
            'shipping_amount' => (string) $shipping,
            'shipping_tax_amount' => (string) $shippingTaxAmount,
            'shipping_tax_id' => $shippingTax?->id,
            'shipping_tax_rate' => $shippingTax?->rate,
            'shipping_tax_name' => $shippingTax?->name,
            'shipping_tax_code' => $shippingTax?->code,
            'tax_total' => (string) $taxTotal,
            'rounding_adjustment' => '0.00',
            'grand_total' => (string) $grand,
        ];

        return ['coupon_eligible' => $totals['coupon_id'] !== null, 'items' => $items, 'totals' => $totals, 'fingerprint' => hash('sha256', json_encode([$items, $totals], JSON_THROW_ON_ERROR))];
    }

    /**
     * Ensure a monetary value stays within the supported range for order totals.
     *
     * @param  BigDecimal  $amount  The amount to validate.
     */
    private function checkLimit(BigDecimal $amount): void
    {
        if ($amount->isGreaterThan('9999999999999.99') || $amount->isLessThan('0')) {
            throw ValidationException::withMessages(['items' => 'Order amounts exceed the supported monetary range.']);
        }
    }
}
