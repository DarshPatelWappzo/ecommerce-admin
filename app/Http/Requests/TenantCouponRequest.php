<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        $action = match ($this->route()->getActionMethod()) {
            'create', 'store' => 'create',
            'edit', 'update', 'status' => 'update',
            'destroy' => 'delete',
            default => 'view',
        };

        return $user instanceof User && $user->status === 'active' && ! $user->is_first_login
            && app(TenantRoleRepository::class)->userHasPermission($user, 'coupons.'.$action);
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'status') {
            return ['is_active' => ['required', 'boolean']];
        }

        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(['active', 'scheduled', 'expired', 'disabled'])],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
        ];
    }
}
