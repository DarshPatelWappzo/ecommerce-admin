<?php

namespace App\Models\Tenant;

use App\Models\TenantDatabase;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\NewAccessToken;

#[Fillable(['first_name', 'last_name', 'email', 'phone_country_code', 'phone', 'customer_type', 'company_name', 'gstin', 'status', 'notes'])]
class Customer extends TenantModel implements \Illuminate\Contracts\Auth\Authenticatable
{
    use Authenticatable, \Laravel\Sanctum\HasApiTokens;
    use HasFactory, SoftDeletes;

    public function createCustomerToken(TenantDatabase $tenant): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();
        $token = $this->tokens()->create(['tenant_database_id' => $tenant->id, 'name' => 'customer-api', 'token' => hash('sha256', $plainTextToken), 'abilities' => ['customer-api'], 'expires_at' => now()->addHour()]);

        return new NewAccessToken($token, $token->id . '|' . $plainTextToken);
    }

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
