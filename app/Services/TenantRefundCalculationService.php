<?php

namespace App\Services;

use App\Models\Tenant\OrderItem;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

class TenantRefundCalculationService
{
    /** @return array<string, string|int> Original discounted merchandise and tax allocation; shipping excluded. */
    public function calculate(OrderItem $item, int $quantity): array
    {
        $item->loadMissing('returns');
        $used = $item->returns->filter(fn($return): bool => app(TenantReturnEligibilityService::class)->consumesQuantity($return));
        $remaining = $item->quantity - $used->sum('quantity');
        if ($quantity < 1 || $quantity > $remaining) {
            throw ValidationException::withMessages(['quantity' => 'The return quantity exceeds the remaining purchased quantity.']);
        }
        $data = ['quantity' => $quantity, 'purchased_quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'shipping_amount' => '0.00'];
        foreach (['subtotal', 'taxable_amount', 'tax_amount'] as $field) {
            $original = TenantOrderCalculationService::money($item->$field);
            $allocated = TenantOrderCalculationService::money('0');
            foreach ($used as $return) {
                $allocated = $allocated->plus($return->calculation[$field] ?? '0');
            }
            $amount = $quantity === $remaining ? $original->minus($allocated) : $original->dividedBy($item->quantity, 2, RoundingMode::Down)->multipliedBy($quantity);
            $data[$field] = (string) $amount;
        }
        $data['discount_amount'] = (string) TenantOrderCalculationService::money($data['subtotal'])->minus($data['taxable_amount']);
        $data['total_amount'] = (string) TenantOrderCalculationService::money($data['taxable_amount'])->plus($data['tax_amount']);

        return $data;
    }
}
