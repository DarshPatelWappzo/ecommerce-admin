<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PackagePayment extends Model
{
    protected $fillable = ['tenant_database_id', 'owner_id', 'package_id', 'quote', 'billing_cycle', 'amount', 'currency', 'gateway', 'mode', 'configuration_version_id', 'reference', 'idempotency_key', 'payload_hash', 'gateway_order_id', 'gateway_payment_id', 'status', 'failure_code', 'paid_at', 'subscription_id', 'checkout_state', 'checkout_expires_at'];

    protected $hidden = ['idempotency_key', 'payload_hash', 'configuration_version_id', 'subscription'];

    protected $attributes = ['status' => 'creating', 'checkout_state' => 'open'];

    protected $appends = ['fulfillment_status', 'message', 'effective_starts_at', 'local_checkout_status'];

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(UserPackage::class);
    }

    public function checkoutDeadline(): Carbon
    {
        return $this->checkout_expires_at ?? $this->created_at->copy()->addMinutes(config('subscriptions.checkout_minutes', 30));
    }

    public function reusable(): bool
    {
        return ! in_array($this->status, ['captured', 'rejected'], true) && $this->checkoutDeadline()->isFuture();
    }

    public function getLocalCheckoutStatusAttribute(): string
    {
        return $this->status === 'captured' ? 'completed' : ($this->checkoutDeadline()->lte(now()) ? 'expired' : $this->checkout_state);
    }

    public function getFulfillmentStatusAttribute(): string
    {
        if ($this->status !== 'captured') {
            return 'unpaid';
        }
        if ($this->failure_code === 'resolution_required' || ! $this->subscription_id) {
            return 'resolution_required';
        }

        return $this->subscription?->start_date->isFuture() ? 'scheduled' : 'activated';
    }

    public function getEffectiveStartsAtAttribute(): ?string
    {
        return $this->subscription_id ? $this->subscription?->start_date->toIso8601String() : null;
    }

    public function getMessageAttribute(): string
    {
        if ($this->status === 'captured') {
            return match ($this->fulfillment_status) {
                'resolution_required' => 'Payment received; resolution required',
                'scheduled' => 'Package scheduled from '.$this->subscription->start_date->format('d M Y H:i T'),
                default => 'Package activated',
            };
        }
        if (in_array($this->status, ['failed', 'rejected'], true)) {
            return 'Payment failed';
        }
        if ($this->local_checkout_status === 'expired') {
            return 'Checkout expired locally; start a new attempt. Any later payment capture will still be verified.';
        }

        return $this->checkout_state === 'dismissed'
            ? 'Checkout dismissed; you can retry or choose another package'
            : 'Payment pending';
    }

    protected function casts(): array
    {
        return ['quote' => 'array', 'amount' => 'integer', 'paid_at' => 'datetime', 'checkout_expires_at' => 'datetime'];
    }
}
