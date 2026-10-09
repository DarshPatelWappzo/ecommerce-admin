<?php

namespace App\Http\Requests;

use App\Models\Tenant\Order;
use Illuminate\Validation\Rule;

class CustomerOrderRequest extends CustomerShoppingRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['sometimes', Rule::in(array_keys(Order::TRANSITIONS))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'razorpay_order_id' => ['required_if:action,verify', 'string', 'max:100', 'regex:/^order_[A-Za-z0-9]+$/'],
            'razorpay_payment_id' => ['required_if:action,verify', 'string', 'max:100', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'razorpay_signature' => ['required_if:action,verify', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'action' => ['sometimes', Rule::in(['initiate', 'verify', 'reconcile'])],
        ];
    }
}
