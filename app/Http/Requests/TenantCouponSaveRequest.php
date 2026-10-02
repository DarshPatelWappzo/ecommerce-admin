<?php

namespace App\Http\Requests;

use App\Models\Tenant\Coupon;
use Illuminate\Validation\Rule;

class TenantCouponSaveRequest extends TenantCouponRequest
{
    protected function prepareForValidation(): void
    {
        $coupon = $this->route('coupon');
        if ($coupon instanceof Coupon) {
            foreach (['starts_at', 'ends_at'] as $field) {
                if (! $this->exists($field)) {
                    $this->merge([$field => $coupon->$field?->toDateTimeString()]);
                }
            }
        }
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $unique = Rule::unique('tenant.coupons', 'code');
        if ($this->route('coupon') instanceof Coupon) {
            $unique->ignore($this->route('coupon'));
        }
        $money = ['numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'];
        $rules = [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/', $unique],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['required', ...$money, ...($this->input('discount_type') === 'percentage' ? ['max:100'] : [])],
            'maximum_discount' => ['nullable', ...$money],
            'minimum_subtotal' => ['required', ...$money],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', ...($this->filled('starts_at') ? ['after_or_equal:starts_at'] : [])],
            'usage_limit' => ['nullable', 'integer', 'between:1,4294967295'],
            'per_customer_limit' => ['nullable', 'integer', 'between:1,4294967295'],
        ];
        foreach (['products', 'categories', 'customers'] as $relation) {
            $rules[$relation] = ['sometimes', 'array', 'max:1000'];
            $rules[$relation.'.*'] = ['integer', 'distinct', Rule::exists('tenant.'.$relation, 'id')->whereNull('deleted_at')];
        }

        return $rules;
    }
}
