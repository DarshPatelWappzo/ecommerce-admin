<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'type', 'is_filterable', 'is_variant', 'status'])]
class ProductAttribute extends TenantModel
{
    protected $table = 'attributes';

    protected $attributes = ['is_filterable' => false, 'is_variant' => false, 'status' => true];

    protected function casts(): array
    {
        return ['is_filterable' => 'boolean', 'is_variant' => 'boolean', 'status' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class, 'attribute_id')->orderBy('sort_order')->orderBy('id');
    }
}
