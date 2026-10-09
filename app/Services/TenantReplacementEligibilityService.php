<?php

namespace App\Services;

use App\Models\Tenant\OrderItem;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\ReplacementRequest;

class TenantReplacementEligibilityService
{
    public function __construct(private readonly TenantReturnEligibilityService $returns) {}

    /** @return array<string, mixed> Delivery-based purchase policy and unclaimed quantity. */
    public function check(OrderItem $item): array
    {
        $item->loadMissing(['order.shipment', 'product', 'returns', 'replacements']);
        $snapshot = $item->replacements->first()?->policy_snapshot;
        $replaceable = $item->is_replaceable ?? $snapshot['is_replaceable'] ?? $item->product?->is_replaceable ?? false;
        $days = $item->is_replaceable !== null ? $item->replacement_days : ($snapshot['replacement_days'] ?? $item->product?->replacement_days);
        $delivery = $item->order->shipment?->delivered_at;
        $deadline = $delivery && $days !== null ? $delivery->copy()->addDays($days)->endOfDay() : null;
        $returned = $item->returns->filter(fn($return): bool => $this->returns->consumesQuantity($return))->sum('quantity');
        $claimed = $item->replacements->whereNotIn('status', ReplacementRequest::RELEASED)->sum('quantity');
        $remaining = max(0, $item->quantity - $returned - $claimed);
        $active = $item->replacements->whereNotIn('status', ReplacementRequest::TERMINAL)->isNotEmpty();
        $variantExists = $item->relationLoaded('variant')
            ? $item->variant !== null && $item->variant->product_id === $item->product_id && $item->variant->sku === $item->sku
            : ProductVariant::where('product_id', $item->product_id)->where('sku', $item->sku)->whereKey($item->product_variant_id)->exists();
        $reason = match (true) {
            ! $replaceable || $days === null => 'PRODUCT_NOT_REPLACEABLE',
            $item->order->status !== 'delivered' || ! $delivery || $delivery->isFuture() => 'ORDER_NOT_DELIVERED',
            now()->gt($deadline) => 'REPLACEMENT_WINDOW_EXPIRED',
            ! $variantExists => 'VARIANT_UNAVAILABLE',
            $active => 'ACTIVE_REPLACEMENT_EXISTS',
            $remaining === 0 => 'NO_ELIGIBLE_QUANTITY',
            default => null,
        };

        return ['eligible' => $reason === null, 'reason' => $reason, 'is_replaceable' => (bool) $replaceable, 'replacement_days' => $days, 'replacement_deadline' => $deadline?->toDateString(), 'delivery_date' => $delivery?->toDateString(), 'remaining_replaceable_quantity' => $remaining, 'policy_source' => $item->is_replaceable !== null ? 'purchase_snapshot' : ($snapshot ? 'replacement_snapshot' : 'legacy_product_fallback')];
    }
}
