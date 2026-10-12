<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['gateway', 'mode', 'test_credentials', 'live_credentials', 'settings', 'is_enabled'])]
class PaymentGateway extends TenantModel
{
    protected $attributes = ['mode' => 'test', 'is_enabled' => false];

    protected $hidden = ['test_credentials', 'live_credentials'];

    protected function casts(): array
    {
        return ['test_credentials' => 'encrypted:array', 'live_credentials' => 'encrypted:array', 'settings' => 'array', 'is_enabled' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PaymentGatewayCredential::class);
    }
}
