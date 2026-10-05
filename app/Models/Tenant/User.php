<?php

namespace App\Models\Tenant;

use App\Models\TenantDatabase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;

#[Fillable(['first_name', 'last_name', 'email', 'mobile_number', 'password', 'status', 'is_first_login', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $connection = 'tenant';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_first_login' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function createTenantToken(TenantDatabase $tenantDatabase): NewAccessToken
    {
        $plainTextToken = $this->generateTokenString();
        $token = $this->tokens()->create([
            'tenant_database_id' => $tenantDatabase->id,
            'name' => 'tenant-api',
            'token' => hash('sha256', $plainTextToken),
            'abilities' => ['tenant-api'],
            'expires_at' => now()->addHour(),
        ]);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
