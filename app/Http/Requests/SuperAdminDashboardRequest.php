<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->is_super_admin === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $periods = match ($this->input('section')) {
            'packages' => ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'this_year', 'all_time'],
            'users' => ['7', '30', '90', 'this_year'],
            'revenue' => ['6', '12'],
            default => [],
        };

        return [
            'section' => ['required', Rule::in(['packages', 'users', 'revenue'])],
            'period' => ['required', 'string', Rule::in($periods)],
            'currency' => ['sometimes', 'required', 'string', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
