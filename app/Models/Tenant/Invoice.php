<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Invoice extends TenantModel
{
    protected $attributes = ['status' => 'draft'];

    protected $fillable = ['order_id', 'number', 'period', 'status', 'mode', 'invoice_date', 'issued_at', 'seller', 'customer', 'billing', 'shipping', 'financials', 'order_fingerprint', 'customer_name', 'currency', 'grand_total', 'notes', 'terms', 'created_by', 'updated_by', 'issued_by'];

    protected function casts(): array
    {
        return ['seller' => 'array', 'customer' => 'array', 'billing' => 'array', 'shipping' => 'array', 'financials' => 'array', 'invoice_date' => 'date', 'issued_at' => 'datetime', 'grand_total' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(function (Invoice $invoice): void {
            if ($invoice->getOriginal('status') === 'issued') {
                throw ValidationException::withMessages(['invoice' => 'Issued invoices are immutable. A cancellation or credit-note workflow is required.']);
            }
        });
        static::deleting(function (Invoice $invoice): void {
            throw ValidationException::withMessages(['invoice' => 'Invoice deletion is not supported.']);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function auditModule(): string
    {
        return 'invoices';
    }
}
