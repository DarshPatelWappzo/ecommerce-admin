<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['package_id', 'billing_cycle', 'duration_months', 'price', 'currency', 'is_active'])]
class PackagePrice extends Model
{
    protected $attributes = ['is_active' => true];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'is_active' => 'boolean', 'duration_months' => 'integer'];
    }
}
