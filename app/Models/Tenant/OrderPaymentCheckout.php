<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'order_id', 'gateway', 'gateway_account', 'gateway_order_id', 'amount', 'currency', 'status', 'requested_at', 'initiated_by'])]
class OrderPaymentCheckout extends TenantModel
{
    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'requested_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderPaymentEvent::class)->orderBy('id');
    }
}
