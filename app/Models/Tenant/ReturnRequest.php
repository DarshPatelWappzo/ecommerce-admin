<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['return_number', 'order_id', 'order_item_id', 'customer_id', 'quantity', 'reason_id', 'reason_note', 'status', 'inventory_disposition', 'refund_amount', 'calculation', 'requested_at', 'approved_at', 'approved_by', 'rejected_at', 'rejected_by', 'received_at', 'received_by', 'inspected_at', 'inspected_by', 'completed_at', 'completed_by', 'admin_note', 'rejection_reason'])]
class ReturnRequest extends TenantModel
{
    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected'],
        'approved' => ['in_transit', 'received', 'cancelled'],
        'in_transit' => ['received'],
        'received' => ['inspection_passed', 'inspection_failed'],
        'inspection_passed' => ['refund_pending', 'closed'],
        'refund_pending' => ['refund_processing'],
        'refund_processing' => ['refunded', 'refund_failed'],
        'refund_failed' => ['refund_processing'],
        'inspection_failed' => ['closed'],
        'refunded' => ['closed'],
        'rejected' => [],
        'cancelled' => [],
        'closed' => [],
    ];

    public const RELEASED = ['rejected', 'cancelled', 'inspection_failed'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'reason_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function auditModule(): string
    {
        return 'returns';
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'refund_amount' => 'decimal:2', 'calculation' => 'array', 'requested_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime', 'received_at' => 'datetime', 'inspected_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
