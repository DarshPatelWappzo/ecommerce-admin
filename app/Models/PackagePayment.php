<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagePayment extends Model
{
    protected $fillable = ['tenant_database_id', 'owner_id', 'package_id', 'quote', 'billing_cycle', 'amount', 'currency', 'gateway', 'mode', 'configuration_version_id', 'reference', 'idempotency_key', 'payload_hash', 'gateway_order_id', 'gateway_payment_id', 'status', 'failure_code', 'paid_at', 'subscription_id'];

    protected $hidden = ['idempotency_key', 'payload_hash', 'configuration_version_id'];

    protected $attributes = ['status' => 'creating'];

    public function getConnectionName(): string
    {
        return config('subscriptions.connection');
    }

    protected function casts(): array
    {
        return ['quote' => 'array', 'amount' => 'integer', 'paid_at' => 'datetime'];
    }
}
