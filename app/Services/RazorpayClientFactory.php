<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class RazorpayClientFactory
{
    public function __construct(private readonly PaymentGatewayService $settings) {}

    public function make(int $versionId): PendingRequest
    {
        $credentials = $this->settings->credentials($versionId);

        return Http::baseUrl('https://api.razorpay.com/v1')
            ->withBasicAuth($credentials['key_id'], $credentials['key_secret'])
            ->acceptJson()->asJson()->connectTimeout(5)->timeout(20)->withoutRedirecting();
    }
}
