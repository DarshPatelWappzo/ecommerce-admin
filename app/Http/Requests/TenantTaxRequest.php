<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;

class TenantTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('tenant');
        $permission = match ($this->route()->getActionMethod()) {
            'create', 'store' => 'taxes.create',
            'edit', 'update' => 'taxes.update',
            default => 'taxes.view',
        };

        return $user instanceof User && $user->status === 'active' && ! $user->is_first_login
            && app(TenantRoleRepository::class)->userHasPermission($user, $permission);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
