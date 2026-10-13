<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class PlatformPaymentException extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly bool $creationRejected = false)
    {
        parent::__construct('Payment confirmation is pending. Retry the same checkout or contact support.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error_code' => 503], 503);
    }
}
