<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'method', 'gateway', 'gateway_account', 'gateway_order_id', 'gateway_payment_id', 'reference_number', 'amount', 'currency', 'status', 'paid_at', 'recorded_by', 'failure_code', 'failure_message', 'authorized_at', 'failed_at', 'verified_at'])]
class OrderPayment extends TenantModel
{
    public function auditModule(): string
    {
        return 'payments';
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'authorized_at' => 'datetime', 'failed_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
