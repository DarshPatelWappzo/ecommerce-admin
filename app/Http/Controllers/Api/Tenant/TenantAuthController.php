<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantApiChangePasswordRequest;
use App\Http\Requests\TenantLoginRequest;
use App\Models\Tenant\User;
use App\Models\TenantDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TenantAuthController extends Controller
{
    /**
     * Return the current session CSRF token without caching the response.
     *
     * @param  Request  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function csrfToken(Request $request): JsonResponse
    {
        $data = [];
        $data['csrf_token'] = $request->session()->token();

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Authenticate the tenant user and issue an API token.
     *
     * @param  TenantLoginRequest  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function login(TenantLoginRequest $request): JsonResponse
    {
        $tenantUser = User::where('email', $request->validated('email'))->first();
        if (! $tenantUser || ! Hash::check($request->validated('password'), $tenantUser->password)
            || $tenantUser->status !== 'active') {
            $data = [];
            $data['message'] = 'The provided credentials are incorrect.';
            $data['error_code'] = 401;

            return response()->json($data, 401);
        }

        $tenantDatabase = $request->attributes->get('tenant_database');
        abort_unless($tenantDatabase instanceof TenantDatabase, 401);
        $token = $tenantUser->createTenantToken($tenantDatabase);

        $data = [];
        $data['message'] = $tenantUser->is_first_login
                ? 'First login detected. Please change your password.'
                : 'Login successful.';
        $data['access_token'] = $token->plainTextToken;
        $data['token_type'] = 'Bearer';
        $data['expires_at'] = $token->accessToken->expires_at->toIso8601String();
        $data['is_first_login'] = $tenantUser->is_first_login;

        return response()->json($data)->header('Cache-Control', 'no-store, private');
    }

    /**
     * Revoke the current tenant API token.
     *
     * @param  Request  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user('sanctum')->currentAccessToken()->delete();

        $data = [];
        $data['message'] = 'Logged out successfully.';

        return response()->json($data);
    }

    /**
     * Change the tenant password and revoke tokens for the current tenant.
     *
     * @param  TenantApiChangePasswordRequest  $request  The incoming request.
     * @return JsonResponse The response for this action.
     */
    public function changePassword(TenantApiChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user('sanctum');
        $tenantDatabaseId = $user->currentAccessToken()->tenant_database_id;
        $user->update([
            'password' => Hash::make($request->validated('password')),
            'is_first_login' => false,
        ]);
        $user->tokens()->where('tenant_database_id', $tenantDatabaseId)->delete();

        $data = [];
        $data['message'] = 'Password changed successfully. Please log in again.';

        return response()->json($data);
    }
}
