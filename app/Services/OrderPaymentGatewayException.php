<?php

namespace App\Services;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class OrderPaymentGatewayException extends RuntimeException implements ShouldntReport
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'The payment provider is temporarily unavailable or the checkout is awaiting reconciliation. Retry the same order later.',
            'error_code' => 503,
        ], 503);
    }
}
