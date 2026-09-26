<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'hsn_code', 'quantity', 'price_overridden', 'unit_price', 'subtotal', 'discount_amount', 'taxable_amount', 'tax_rate', 'tax_name', 'tax_code', 'cgst_amount', 'sgst_amount', 'igst_amount', 'tax_amount', 'total_amount'])]
class OrderItem extends TenantModel
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'price_overridden' => 'boolean', 'tax_rate' => 'decimal:4', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'taxable_amount' => 'decimal:2', 'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
