<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUserStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'mobile_number' => ['required', 'string', 'max:30'],
            'role_id' => ['required', 'integer', Rule::exists('tenant.roles', 'id')->where('status', true)],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'Enter a valid first name.',
            'first_name.max' => 'First name is too long.',
            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Enter a valid last name.',
            'last_name.max' => 'Last name is too long.',
            'email.required' => 'Email is required.',
            'email.email' => 'Enter a valid email.',
            'email.max' => 'Email is too long.',
            'email.unique' => 'Email already exists.',
            'mobile_number.required' => 'Mobile number is required.',
            'mobile_number.string' => 'Enter a valid mobile number.',
            'mobile_number.max' => 'Mobile number is too long.',
            'role_id.required' => 'Select a role.',
            'role_id.integer' => 'Select a valid role.',
            'role_id.exists' => 'The selected role is invalid or inactive.',
            'status.required' => 'Select a status.',
            'status.in' => 'Select a valid status.',
        ];
    }
}
