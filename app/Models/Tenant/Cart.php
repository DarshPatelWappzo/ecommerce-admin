<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'active_customer_id', 'status', 'coupon_code', 'expires_at'])]
class Cart extends TenantModel
{
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(CartOrder::class);
    }
}
