<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['first_name', 'last_name', 'email', 'phone_country_code', 'phone', 'customer_type', 'company_name', 'gstin', 'status', 'notes'])]
class Customer extends TenantModel
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['customer_type' => 'individual', 'status' => 'active'];

    protected $hidden = ['notes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'phone_verified_at' => 'datetime'];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderBy('id');
    }

    public function auditModule(): string
    {
        return 'customers';
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
