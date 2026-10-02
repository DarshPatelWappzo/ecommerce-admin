<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['order_payment_checkout_id', 'event_id', 'type', 'payload_hash', 'gateway_payment_id', 'processed_at'])]
class OrderPaymentEvent extends TenantModel
{
    public $timestamps = false;

    protected $hidden = ['payload_hash'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
