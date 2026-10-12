<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_gateway_id', 'mode', 'key_id', 'credentials', 'is_current'])]
class PaymentGatewayCredential extends TenantModel
{
    protected $hidden = ['credentials'];

    protected $attributes = ['is_current' => true];

    protected function casts(): array
    {
        return ['credentials' => 'encrypted:array', 'is_current' => 'boolean'];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }
}
