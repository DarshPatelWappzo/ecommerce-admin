<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cart_id', 'order_id', 'items_snapshot', 'payment_method', 'converted_at'])]
class CartOrder extends TenantModel
{
    use HasFactory;

    protected function casts(): array
    {
        return ['items_snapshot' => 'array', 'converted_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
