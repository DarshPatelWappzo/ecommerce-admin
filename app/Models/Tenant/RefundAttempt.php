<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['refund_id', 'idempotency_key', 'gateway_refund_id', 'status', 'failure_code', 'failure_reason'])]
class RefundAttempt extends TenantModel
{
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }
}
