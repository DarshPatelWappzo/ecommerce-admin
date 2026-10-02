<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TenantOrderActionRequest extends TenantOrderRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
        if (is_string($this->input('reference_number'))) {
            $this->merge(['reference_number' => strtoupper(trim($this->input('reference_number')))]);
        }
    }

    public function rules(): array
    {
        return self::rulesFor($this->route()->getActionMethod());
    }

    public static function rulesFor(string $action): array
    {
        return match ($action) {
            'payments' => [
                'idempotency_key' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
                'method' => ['required', Rule::in(['cod', 'cash', 'bank_transfer'])],
                'reference_number' => ['required', 'string', 'max:100'],
                'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
                'currency' => ['required', Rule::in(['INR'])],
            ],
            'cancel' => ['comment' => ['required', 'string', 'max:2000']],
            'ship' => [
                'courier_name' => ['nullable', 'string', 'max:100'],
                'tracking_number' => ['nullable', 'string', 'max:100'],
                'tracking_url' => ['nullable', 'url:http,https', 'max:1000'],
                'comment' => ['nullable', 'string', 'max:2000'],
            ],
            default => ['comment' => ['nullable', 'string', 'max:2000']],
        };
    }
}
