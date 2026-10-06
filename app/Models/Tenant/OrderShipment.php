<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'replacement_request_id', 'shipment_key', 'courier_name', 'tracking_number', 'tracking_url', 'status', 'shipped_at', 'delivered_at'])]
class OrderShipment extends TenantModel
{
    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function replacementRequest(): BelongsTo
    {
        return $this->belongsTo(ReplacementRequest::class);
    }
}
