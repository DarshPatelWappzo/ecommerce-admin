<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'method', 'gateway', 'gateway_account', 'gateway_order_id', 'gateway_payment_id', 'reference_number', 'amount', 'currency', 'status', 'paid_at', 'recorded_by'])]
class OrderPayment extends TenantModel
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
