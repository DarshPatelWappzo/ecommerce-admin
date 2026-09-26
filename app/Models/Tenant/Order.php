<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'company_name', 'gstin', 'order_date', 'source', 'status', 'payment_status', 'currency', 'subtotal', 'discount_total', 'shipping_amount', 'shipping_tax_amount', 'tax_total', 'rounding_adjustment', 'grand_total', 'shipping_tax_id', 'shipping_tax_rate', 'shipping_tax_name', 'shipping_tax_code', 'customer_note', 'internal_note', 'created_by', 'cancelled_at', 'cancellation_reason', 'draft_input', 'pricing_fingerprint'])]
class Order extends TenantModel
{
    use HasFactory;

    public const TRANSITIONS = ['draft' => ['pending', 'confirmed', 'cancelled'], 'pending' => ['confirmed', 'cancelled'], 'confirmed' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered'], 'delivered' => [], 'cancelled' => []];

    protected $hidden = ['draft_input', 'pricing_fingerprint'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class)->orderBy('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('id');
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(OrderShipment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function auditModule(): string
    {
        return 'orders';
    }

    protected function casts(): array
    {
        return ['order_date' => 'datetime', 'cancelled_at' => 'datetime', 'draft_input' => 'array', 'shipping_tax_rate' => 'decimal:4', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'shipping_amount' => 'decimal:2', 'shipping_tax_amount' => 'decimal:2', 'tax_total' => 'decimal:2', 'rounding_adjustment' => 'decimal:2', 'grand_total' => 'decimal:2'];
    }
}
