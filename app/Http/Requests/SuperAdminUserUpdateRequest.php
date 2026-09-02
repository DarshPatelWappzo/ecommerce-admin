<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\UserDomain;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class SuperAdminUserUpdateRequest extends SuperAdminUserStoreRequest
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
        $user = $this->route('user');
        $userId = $user instanceof User ? $user->id : 0;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['required', 'string', 'max:30'],
            'domains' => ['required', 'array', 'min:1'],
            'domains.*' => [
                'required',
                'string',
                'max:255',
                'distinct',
                Rule::unique(UserDomain::class, 'domain_name')
                    ->where(fn (Builder $query): Builder => $query->whereNull('deleted_at')->where('user_id', '!=', $userId)),
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
