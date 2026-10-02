<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['product_id', 'sku', 'barcode', 'price', 'special_price', 'special_price_from', 'special_price_to', 'cost_price', 'weight', 'quantity', 'reserved_quantity', 'reorder_level', 'status', 'combination_key'])]
class ProductVariant extends TenantModel
{
    use SoftDeletes;

    protected $hidden = ['combination_key'];

    protected $attributes = ['quantity' => 0, 'reserved_quantity' => 0, 'reorder_level' => 0, 'status' => true];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'special_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'special_price_from' => 'date:Y-m-d',
            'special_price_to' => 'date:Y-m-d',
            'status' => 'boolean',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(VariantAttributeValue::class, 'variant_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id');
    }
}
