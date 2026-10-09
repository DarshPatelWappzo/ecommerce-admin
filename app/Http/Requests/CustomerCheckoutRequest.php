<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerCheckoutRequest extends CustomerShoppingRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function rules(): array
    {
        $method = $this->route()->getActionMethod();

        return [
            ...parent::rules(),
            'shipping_address_id' => ['nullable', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'min:1'],
            'coupon_code' => [$method === 'applyCoupon' ? 'required' : 'prohibited', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'payment_method' => [$method === 'place' ? 'required' : 'sometimes', 'string', Rule::in(array_keys(config('order_payments.methods', [])))],
            'idempotency_key' => [$method === 'place' ? 'required' : 'sometimes', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'expected_pricing_fingerprint' => [$method === 'place' ? 'required' : 'sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
