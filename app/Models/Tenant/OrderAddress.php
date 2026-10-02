<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'type', 'name', 'phone', 'address_line_1', 'address_line_2', 'city', 'state_name', 'state_code', 'country_code', 'postal_code'])]
class OrderAddress extends TenantModel
{
    protected function casts(): array
    {
        return [];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
