<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TenantUserUpdateRequest extends TenantUserStoreRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('tenant');
        $roles = app(TenantRoleRepository::class);

        return $user instanceof User && $user->status === 'active'
            && $roles->userHasPermission($user, 'users.update')
            && $roles->userHasPermission($user, 'roles.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'role_id' => ['required', 'integer', Rule::exists('tenant.roles', 'id')->where('status', true)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
