<?php

namespace App\Http\Requests;

use App\Models\Tenant\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRoleUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Role::class, 'name')->ignore($role)],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['integer', Rule::exists('tenant.permissions', 'id')],
            'status' => ['required', 'boolean'],
        ];
    }
}
