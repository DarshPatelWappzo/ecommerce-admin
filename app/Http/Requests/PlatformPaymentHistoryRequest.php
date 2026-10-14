<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformPaymentHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->is_super_admin === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['mode' => $this->input('mode') ?: 'test']); // instead of 'test' write 'live' on production
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'tenant_id' => ['nullable', 'integer', 'min:1'],
            'domain' => ['nullable', 'string', 'max:191'],
            'package_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['creating', 'pending', 'authorized', 'failed', 'rejected', 'captured'])],
            'fulfillment' => ['nullable', Rule::in(['unpaid', 'activated', 'scheduled', 'resolution_required'])],
            'gateway' => ['nullable', 'string', 'max:30', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'mode' => ['required', Rule::in(['live', 'test'])],
            'billing_cycle' => ['nullable', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'sort' => ['nullable', Rule::in(['id', 'amount', 'paid_at', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([15, 30, 50, 100])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
