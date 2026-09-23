<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['label', 'recipient_name', 'phone_country_code', 'phone', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state_code', 'country_code', 'postal_code', 'is_default_shipping', 'is_default_billing'])]
class CustomerAddress extends TenantModel
{
    use HasFactory;

    protected $attributes = ['is_default_shipping' => false, 'is_default_billing' => false];

    protected function casts(): array
    {
        return ['is_default_shipping' => 'boolean', 'is_default_billing' => 'boolean'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function auditModule(): string
    {
        return 'customer_addresses';
    }
}
