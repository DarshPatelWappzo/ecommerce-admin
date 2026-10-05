<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['refund_number', 'return_request_id', 'order_id', 'payment_id', 'amount', 'currency', 'payment_method', 'gateway', 'gateway_refund_id', 'status', 'manual_method', 'reference_number', 'admin_note', 'failure_code', 'failure_reason', 'initiated_at', 'processed_at', 'failed_at', 'created_by'])]
class Refund extends TenantModel
{
    public function payment(): BelongsTo
    {
        return $this->belongsTo(OrderPayment::class, 'payment_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(RefundAttempt::class);
    }

    public function auditModule(): string
    {
        return 'refunds';
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'initiated_at' => 'datetime', 'processed_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
