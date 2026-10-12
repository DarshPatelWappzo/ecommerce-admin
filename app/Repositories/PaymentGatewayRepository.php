<?php

namespace App\Repositories;

use App\Models\Tenant\PaymentGateway;
use App\Models\Tenant\PaymentGatewayCredential;

class PaymentGatewayRepository
{
    public function findByGateway(string $gateway = 'razorpay', bool $lock = false): ?PaymentGateway
    {
        return PaymentGateway::where('gateway', $gateway)->when($lock, fn ($query) => $query->lockForUpdate())->first();
    }

    public function lockForSave(): PaymentGateway
    {
        PaymentGateway::firstOrCreate(['gateway' => 'razorpay']);

        return $this->findByGateway(lock: true);
    }

    public function current(PaymentGateway $gateway, string $mode): ?PaymentGatewayCredential
    {
        return $gateway->versions()->where('mode', $mode)->where('is_current', true)->first();
    }

    public function save(PaymentGateway $gateway): void
    {
        $gateway->save();
    }
}
