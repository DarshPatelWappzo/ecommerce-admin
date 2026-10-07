<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'module', 'action', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'metadata', 'ip_address', 'user_agent'])]
class AuditLog extends TenantModel
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'metadata' => 'array'];
    }
}
