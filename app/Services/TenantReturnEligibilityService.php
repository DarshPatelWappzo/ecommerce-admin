<?php

namespace App\Services;

use App\Models\Tenant\OrderItem;
use App\Models\Tenant\ReplacementRequest;
use App\Models\Tenant\ReturnRequest;

class TenantReturnEligibilityService
{
    /** @return array<string, mixed> Authoritative policy, deadline and available quantity. */
    public function check(OrderItem $item): array
    {
        $item->loadMissing(['order.shipment', 'returns', 'product']);
        $returnable = $item->is_returnable ?? $item->product?->is_returnable ?? false;
        $days = $item->is_returnable !== null ? $item->return_days : $item->product?->return_days;
        $delivery = $item->order->shipment?->delivered_at;
        $reserved = $item->returns->filter(fn(ReturnRequest $return): bool => $this->consumesQuantity($return));
        $replacementQuantity = $item->relationLoaded('replacements')
            ? $item->replacements->whereNotIn('status', ReplacementRequest::RELEASED)->sum('quantity')
            : $item->replacements()->whereNotIn('status', ReplacementRequest::RELEASED)->sum('quantity');
        $remaining = max(0, $item->quantity - $reserved->sum('quantity') - $replacementQuantity);
        $deadline = $delivery && $days !== null ? $delivery->copy()->addDays($days)->endOfDay() : null;
        $reason = match (true) {
            ! $returnable || $days === null => 'PRODUCT_NOT_RETURNABLE',
            $item->order->status !== 'delivered' || ! $delivery => 'ORDER_NOT_DELIVERED',
            now()->gt($deadline) => 'RETURN_WINDOW_EXPIRED',
            $remaining === 0 && $reserved->contains(fn($return) => ! in_array($return->status, ['refunded', 'closed'], true)) => 'ACTIVE_RETURN_EXISTS',
            $remaining === 0 => 'ALREADY_FULLY_RETURNED',
            default => null,
        };

        return ['eligible' => $reason === null, 'reason' => $reason, 'return_days' => $days, 'return_deadline' => $deadline?->toDateString(), 'delivery_date' => $delivery?->toDateString(), 'remaining_returnable_quantity' => $remaining, 'policy_source' => $item->is_returnable !== null ? 'purchase_snapshot' : 'legacy_product_fallback'];
    }

    public function consumesQuantity(ReturnRequest $return): bool
    {
        return ! in_array($return->status, ReturnRequest::RELEASED, true)
            && ! ($return->status === 'closed' && $return->inventory_disposition === 'inspection_failed');
    }
}
