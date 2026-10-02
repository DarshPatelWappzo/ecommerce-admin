<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['variant_id', 'image', 'alt_text', 'is_primary', 'sort_order'])]
class ProductImage extends TenantModel
{
    protected $appends = ['url'];

    protected $hidden = ['image'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image);
    }
}
