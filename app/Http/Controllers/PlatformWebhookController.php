<?php

namespace App\Http\Controllers;

use App\Services\PackageCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformWebhookController extends Controller
{
    /** Authenticate the raw versioned webhook and acknowledge only committed processing. */
    public function __invoke(Request $request, int $version, PackageCheckoutService $checkout): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 262144, 413);
        $checkout->webhook($version, (string) $request->header('X-Razorpay-Event-Id'), $request->getContent(), (string) $request->header('X-Razorpay-Signature'));
        $data = ['message' => 'Accepted'];

        return response()->json($data);
    }
}
