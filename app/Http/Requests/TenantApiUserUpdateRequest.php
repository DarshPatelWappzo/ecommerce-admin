<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Http\Exceptions\HttpResponseException;

class TenantApiUserUpdateRequest extends TenantUserUpdateRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');
        $roleRepository = app(TenantRoleRepository::class);

        if (! $user instanceof User || $user->status !== 'active'
            || ! $roleRepository->userHasPermission($user, 'users.update')) {
            throw new HttpResponseException(response()->json([
                'message' => 'You need users.update permission to update users.',
                'error_code' => 403,
            ], 403));
        }

        if ($user->is_first_login) {
            throw new HttpResponseException(response()->json([
                'message' => 'Please change your password before updating users.',
                'error_code' => 403,
            ], 403));
        }

        return true;
    }
}
