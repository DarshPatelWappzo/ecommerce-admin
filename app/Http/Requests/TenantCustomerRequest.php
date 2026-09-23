<?php

namespace App\Http\Requests;

use App\Models\Tenant\Customer;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantCustomerRequest extends FormRequest
{
    protected function permission(): string
    {
        return match ($this->route()->getActionMethod()) {
            'create', 'store' => 'customers.create',
            'edit', 'update', 'status' => 'customers.update',
            'destroy' => 'customers.delete',
            default => 'customers.view',
        };
    }

    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');

        return $user instanceof User && $user->status === 'active' && ! $user->is_first_login
            && app(TenantRoleRepository::class)->userHasPermission($user, $this->permission());
    }

    public function customer(): ?Customer
    {
        return $this->route('customer') === null ? null : Customer::findOrFail($this->route('customer'));
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'status') {
            return ['status' => ['required', Rule::in(['active', 'inactive'])]];
        }

        return [
            'search' => ['nullable', 'string', 'max:254'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'customer_type' => ['nullable', Rule::in(['individual', 'business'])],
            'joined_from' => ['nullable', 'date_format:Y-m-d'],
            'joined_to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('joined_from') ? ['after_or_equal:joined_from'] : [])],
            'sort' => ['nullable', Rule::in(['created_at', 'customer_code', 'first_name'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
