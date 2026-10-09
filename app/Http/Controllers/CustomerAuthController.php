<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOtpRequest;
use App\Services\CustomerOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    /** Send a sign-in code to an existing active customer without disclosing account existence.
     * @param  CustomerOtpRequest  $request  Validated email and resolved tenant.
     * @param  CustomerOtpService  $otp  Tenant-scoped sign-in service.
     * @return JsonResponse Generic acknowledgement.
     */
    public function requestCode(CustomerOtpRequest $request, CustomerOtpService $otp): JsonResponse
    {
        $otp->request($request->validated('email'), $request->attributes->get('tenant_database')->id);

        $data = ['message' => 'If an active customer account exists, a sign-in code has been sent.'];

        return response()->json($data);
    }

    /** Exchange a single-use sign-in code for a tenant-bound customer token.
     * @param  CustomerOtpRequest  $request  Validated email and code.
     * @param  CustomerOtpService  $otp  Sign-in service.
     * @return JsonResponse Short-lived bearer token.
     */
    public function verify(CustomerOtpRequest $request, CustomerOtpService $otp): JsonResponse
    {
        $token = $otp->verify($request->validated('email'), $request->validated('code'), $request->attributes->get('tenant_database'));
        $data = ['data' => ['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600]];

        return response()->json($data);
    }

    /** Revoke the current customer token.
     * @param  Request  $request  Authenticated customer.
     * @return JsonResponse Logout acknowledgement.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        $data = ['message' => 'Signed out.'];

        return response()->json($data);
    }
}
