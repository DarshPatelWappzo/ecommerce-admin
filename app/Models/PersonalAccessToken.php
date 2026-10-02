<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = ['name', 'token', 'abilities', 'expires_at', 'tenant_database_id'];

    public function getConnectionName(): ?string
    {
        return config('database.default');
    }

    public function tenantDatabase(): BelongsTo
    {
        return $this->belongsTo(TenantDatabase::class);
    }
}
