<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['replacement_request_id', 'from_status', 'to_status', 'changed_by', 'customer_id', 'note', 'created_at'])]
class ReplacementStatusHistory extends TenantModel
{
    public $timestamps = false;

    public function replacementRequest(): BelongsTo
    {
        return $this->belongsTo(ReplacementRequest::class);
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
