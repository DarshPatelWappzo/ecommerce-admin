<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'description', 'is_active', 'discount_type', 'discount_value', 'maximum_discount', 'minimum_subtotal', 'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit'])]
class Coupon extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true, 'minimum_subtotal' => '0.00'];

    protected $appends = ['status'];

    public function getStatusAttribute(): string
    {
        return match (true) {
            ! $this->is_active, $this->trashed() => 'disabled',
            $this->starts_at?->isFuture() === true => 'scheduled',
            $this->ends_at?->isPast() === true => 'expired',
            default => 'active',
        };
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_products')->withTrashed();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_categories')->withTrashed();
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'coupon_customers')->withTrashed();
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'discount_value' => 'decimal:2', 'maximum_discount' => 'decimal:2', 'minimum_subtotal' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'usage_limit' => 'integer', 'per_customer_limit' => 'integer'];
    }
}
