<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['replacement_number', 'order_id', 'order_item_id', 'customer_id', 'quantity', 'reason_id', 'reason_note', 'admin_note', 'status', 'inventory_disposition', 'stock_reserved', 'return_request_id', 'policy_snapshot', 'requested_at', 'approved_at', 'rejected_at', 'picked_up_at', 'received_at', 'qc_completed_at', 'processing_at', 'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at', 'out_of_stock_at', 'converted_at'])]
class ReplacementRequest extends TenantModel
{
    protected $attributes = ['stock_reserved' => false];

    public const TRANSITIONS = [
        'requested' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['picked_up', 'cancelled'],
        'picked_up' => ['received'],
        'received' => ['qc_passed', 'qc_failed'],
        'qc_passed' => ['replacement_processing'],
        'replacement_processing' => ['shipped'],
        'shipped' => ['delivered'],
        'delivered' => ['completed'],
        'out_of_stock' => ['replacement_processing', 'converted_to_refund'],
        'completed' => [],
        'rejected' => [],
        'cancelled' => [],
        'qc_failed' => [],
        'converted_to_refund' => [],
    ];

    public const RELEASED = ['rejected', 'cancelled', 'converted_to_refund'];

    public const TERMINAL = ['completed', 'rejected', 'cancelled', 'qc_failed', 'converted_to_refund'];

    public const DISPOSITIONS = ['restock', 'damaged', 'defective', 'quarantine', 'do_not_restock'];

    public static function permission(string $target): string
    {
        return match ($target) {
            'approved' => 'replacements.approve',
            'rejected' => 'replacements.reject',
            'cancelled' => 'replacements.cancel',
            'qc_passed', 'qc_failed' => 'replacements.qc',
            'converted_to_refund' => 'refunds.initiate',
            default => 'replacements.update_status',
        };
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class, 'reason_id');
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(OrderShipment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReplacementStatusHistory::class)->orderBy('id');
    }

    public function auditModule(): string
    {
        return 'replacements';
    }

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'stock_reserved' => 'boolean', 'policy_snapshot' => 'array', ...array_fill_keys(['requested_at', 'approved_at', 'rejected_at', 'picked_up_at', 'received_at', 'qc_completed_at', 'processing_at', 'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at', 'out_of_stock_at', 'converted_at'], 'datetime')];
    }
}
