<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'code', 'rate', 'description', 'is_active'])]
class Tax extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['is_active' => true];

    protected $hidden = ['deleted_at'];

    protected $appends = ['is_available'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function isAvailable(): Attribute
    {
        return Attribute::get(fn (): bool => $this->is_active && ! $this->trashed());
    }

    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'is_active' => 'boolean'];
    }
}
