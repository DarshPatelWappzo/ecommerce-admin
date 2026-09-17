<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'domain_id', 'database_name', 'status', 'migrated_at', 'provisioned_at', 'provisioning_error'])]
class TenantDatabase extends Model
{
    protected function casts(): array
    {
        return [
            'migrated_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(UserDomain::class, 'domain_id');
    }
}
