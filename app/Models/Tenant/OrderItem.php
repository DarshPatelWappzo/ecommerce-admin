<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'hsn_code', 'quantity', 'price_overridden', 'unit_price', 'subtotal', 'discount_amount', 'coupon_discount', 'taxable_amount', 'tax_rate', 'tax_name', 'tax_code', 'cgst_amount', 'sgst_amount', 'igst_amount', 'tax_amount', 'total_amount', 'is_returnable', 'return_days', 'is_replaceable', 'replacement_days'])]
class OrderItem extends TenantModel
{
    protected function casts(): array
    {
        return ['is_returnable' => 'boolean', 'return_days' => 'integer', 'is_replaceable' => 'boolean', 'replacement_days' => 'integer', 'coupon_discount' => 'decimal:2', 'quantity' => 'integer', 'price_overridden' => 'boolean', 'tax_rate' => 'decimal:4', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'taxable_amount' => 'decimal:2', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
