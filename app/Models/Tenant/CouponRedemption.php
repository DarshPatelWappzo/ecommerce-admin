<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['coupon_id', 'order_id', 'customer_id', 'released_at'])]
class CouponRedemption extends TenantModel
{
    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }
}
