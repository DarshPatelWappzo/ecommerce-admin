<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('web')?->is_super_admin;
    }

    public function rules(): array
    {
        return $this->isMethod('GET') ? [] : $this->configurationRules();
    }

    public function configurationRules(): array
    {
        return [
            'mode' => ['required', Rule::in(['test', 'live'])],
            'is_enabled' => ['required', 'boolean'],
            'key_id' => ['nullable', 'string', 'max:100'],
            'key_secret' => ['nullable', 'string', 'max:500'],
            'webhook_secret' => ['nullable', 'string', 'max:500']
        ];
    }
}
