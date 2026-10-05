<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class InvoiceItem extends TenantModel
{
    protected $fillable = ['invoice_id', 'order_item_id', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        $guard = function (InvoiceItem $item): void {
            if ($item->invoice()->value('status') === 'issued') {
                throw ValidationException::withMessages(['invoice' => 'Issued invoice items are immutable.']);
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }
}
