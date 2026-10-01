<?php

namespace App\Services;

use App\Models\Tenant\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TenantInvoiceSnapshotBuilder
{
    public const TOTALS = ['subtotal', 'discount_total', 'coupon_discount', 'shipping_amount', 'shipping_tax_amount', 'shipping_tax_rate', 'shipping_tax_name', 'shipping_tax_code', 'tax_total', 'rounding_adjustment', 'grand_total'];

    /** Build only from saved order data; never consult live catalog or customer records. */
    public function build(Order $order): array
    {
        $order->load(['items', 'addresses']);
        $financials = $order->only(self::TOTALS);
        $items = $order->items->map(fn($item): array => $item->only(['id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'hsn_code', 'quantity', 'unit_price', 'subtotal', 'discount_amount', 'coupon_discount', 'taxable_amount', 'tax_rate', 'tax_name', 'tax_code', 'cgst_amount', 'sgst_amount', 'igst_amount', 'tax_amount', 'total_amount']))->all();
        $zero = TenantOrderCalculationService::money('0');
        $subtotal = $discount = $taxable = $tax = $lines = $zero;
        if ($items === []) {
            $this->invalid();
        }
        foreach ($items as $item) {
            foreach (['quantity', 'unit_price', 'subtotal', 'discount_amount', 'coupon_discount', 'taxable_amount', 'tax_rate', 'tax_amount', 'total_amount', 'product_name', 'tax_code'] as $field) {
                if (! isset($item[$field]) || $item[$field] === '') {
                    $this->invalid();
                }
            }
            $money = fn(string $field) => TenantOrderCalculationService::money((string) $item[$field]);
            if (
                $item['quantity'] < 1 || ! $money('unit_price')->multipliedBy($item['quantity'])->isEqualTo($money('subtotal'))
                || ! $money('subtotal')->minus($money('discount_amount'))->isEqualTo($money('taxable_amount'))
                || ! $money('taxable_amount')->plus($money('tax_amount'))->isEqualTo($money('total_amount'))
            ) {
                $this->invalid();
            }
            $components = Arr::only($item, ['cgst_amount', 'sgst_amount', 'igst_amount']);
            if (count(array_filter($components, fn($amount) => $amount !== null)) > 0) {
                $componentTotal = $zero;
                foreach ($components as $amount) {
                    $componentTotal = $componentTotal->plus($amount ?? '0');
                }
                if (! $componentTotal->isEqualTo($money('tax_amount'))) {
                    $this->invalid();
                }
            }
            $subtotal = $subtotal->plus($money('subtotal'));
            $discount = $discount->plus($money('discount_amount'));
            $taxable = $taxable->plus($money('taxable_amount'));
            $tax = $tax->plus($money('tax_amount'));
            $lines = $lines->plus($money('total_amount'));
        }
        $expected = $lines->plus($order->shipping_amount)->plus($order->shipping_tax_amount)->plus($order->rounding_adjustment);
        if (
            ! $subtotal->isEqualTo($order->subtotal) || ! $discount->isEqualTo($order->discount_total)
            || ! $tax->plus($order->shipping_tax_amount)->isEqualTo($order->tax_total) || ! $expected->isEqualTo($order->grand_total)
        ) {
            $this->invalid();
        }
        if (TenantOrderCalculationService::money($order->shipping_tax_amount)->isGreaterThan('0') && $order->shipping_tax_rate === null) {
            $this->invalid();
        }
        $financials['taxable_amount'] = (string) $taxable->plus($order->shipping_amount);
        $addresses = [];
        foreach (['billing', 'shipping'] as $type) {
            $address = $order->addresses->firstWhere('type', $type);
            if (! $address || ! $address->address_line_1 || ! $address->state_code || ! $address->country_code) {
                $this->invalid();
            }
            $addresses[$type] = $address->only(['name', 'phone', 'address_line_1', 'address_line_2', 'city', 'state_name', 'state_code', 'country_code', 'postal_code']);
        }
        $snapshot = [
            'order_number' => $order->order_number,
            'currency' => $order->currency,
            'customer' => $order->only(['customer_id', 'customer_name', 'customer_email', 'customer_phone', 'company_name', 'gstin']),
            ...$addresses,
            'financials' => $financials,
            'items' => $items,
        ];
        $snapshot['fingerprint'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));

        return $snapshot;
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['order' => 'The saved order snapshot is missing or inconsistent. Correct the order through the validated order workflow before invoicing.']);
    }

    public function settings(): array
    {
        return config('invoices.tenants.' . config('database.connections.tenant.database'), []);
    }

    public function seller(): array
    {
        return Arr::only($this->settings(), ['name', 'address', 'country_code', 'state_code', 'tax_registered', 'gstin']);
    }
}
