<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'grace_period_days',
    'slug',
    'min_monthly_users',
    'max_monthly_users',
    'cpu_vcores',
    'ram_gb',
    'application_servers',
    'database_type',
    'infrastructure_summary',
    'min_monthly_cost',
    'max_monthly_cost',
    'currency',
    'billing_period',
    'bandwidth_gb',
    'storage_gb',
    'backup_included',
    'cdn_included',
    'load_balancer_included',
    'description',
    'cost_disclaimer',
    'sort_order',
    'is_recommended',
    'status',
    'is_tenant_visible',
    'public_infrastructure',
])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $attributes = ['is_tenant_visible' => true, 'public_infrastructure' => false, 'grace_period_days' => 7];

    protected function casts(): array
    {
        return ['grace_period_days' => 'integer'];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PackagePrice::class);
    }

    public function limits(): HasMany
    {
        return $this->hasMany(PackageLimit::class);
    }

    public function userPackages(): HasMany
    {
        return $this->hasMany(UserPackage::class);
    }
}
