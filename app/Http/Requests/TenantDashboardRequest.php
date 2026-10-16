<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TenantDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('tenant');

        return $user instanceof User && $user->status === 'active' && ! $user->is_first_login
            && app(TenantRoleRepository::class)->userHasPermission($user, 'dashboard.view');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'required', Rule::in(['today', '7', '30', 'this_month', 'this_year', 'custom'])],
            'from' => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (
                ! $validator->errors()->any() && $this->input('period') === 'custom'
                && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($this->input('to'))) > 365
            ) {
                $validator->errors()->add('to', 'Select a date range of 366 days or less.');
            }
        }];
    }
}
