<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;

class SavePaymentGatewayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('tenant');
        $permission = $this->route()?->getActionMethod() === 'index' ? 'payment_gateways.view' : 'payment_gateways.update';

        return $user instanceof User && $user->status === 'active' && ! $user->is_first_login
            && app(TenantRoleRepository::class)->userHasPermission($user, $permission);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        if (in_array($this->route()?->getActionMethod(), ['index', 'retire'], true)) {
            return [];
        }
        $rules = ['mode' => ['required', 'in:test,live']];
        if ($this->route()?->getActionMethod() === 'testConnection') {
            return $rules;
        }

        return $this->configurationRules();
    }

    /** @return array<string, array<int, string>> Shared server and client settings rules. */
    public function configurationRules(): array
    {
        $rules = ['mode' => ['required', 'in:test,live'], 'is_enabled' => ['required', 'boolean']];
        $rules['bind_legacy'] = ['sometimes', 'boolean'];
        foreach (['test', 'live'] as $mode) {
            $rules[$mode.'_key_id'] = ['nullable', 'string', 'max:50', 'regex:/^rzp_'.$mode.'_[A-Za-z0-9]+$/'];
            $rules[$mode.'_key_secret'] = ['nullable', 'string', 'max:255'];
            $rules[$mode.'_webhook_secret'] = ['nullable', 'string', 'max:255'];
            $rules[$mode.'_remove_webhook_secret'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
