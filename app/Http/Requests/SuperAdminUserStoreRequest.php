<?php

namespace App\Http\Requests;

use App\Models\UserDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminUserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $domains = $this->input('domains');

        if (is_array($domains)) {
            $this->merge([
                'domains' => array_map(
                    static fn (mixed $domain): mixed => is_string($domain) ? Str::lower(trim($domain)) : $domain,
                    $domains,
                ),
            ]);
        }
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
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'domains' => ['required', 'array', 'min:1'],
            'domains.*' => [
                'required',
                'string',
                'max:255',
                'distinct',
                Rule::unique(UserDomain::class, 'domain_name')->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

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
            'domains.required' => 'Add at least one domain.',
            'domains.array' => 'Enter valid domains.',
            'domains.min' => 'Add at least one domain.',
            'domains.*.required' => 'Domain is required.',
            'domains.*.string' => 'Enter a valid domain.',
            'domains.*.max' => 'Domain is too long.',
            'domains.*.distinct' => 'Each domain must be unique.',
            'domains.*.unique' => 'Domain already exists.',
            'status.required' => 'Select a status.',
            'status.in' => 'Select a valid status.',
        ];
    }
}
