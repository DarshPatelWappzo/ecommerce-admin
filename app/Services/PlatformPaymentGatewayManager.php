<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PlatformPaymentGatewayManager
{
    public function __construct(private readonly PlatformRazorpayGateway $razorpay) {}

    public function resolve(string $gateway): PlatformPaymentGateway
    {
        return match ($gateway) {
            'razorpay' => $this->razorpay,
            default => throw ValidationException::withMessages(['gateway' => 'The platform gateway is unavailable.']),
        };
    }
}
