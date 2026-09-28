<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class TenantOrderSaveRequest extends TenantOrderRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
        if (is_string($this->input('gstin'))) {
            $this->merge(['gstin' => strtoupper(trim($this->input('gstin')))]);
        }
    }

    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }
        if ($this->input('submit_as') === 'confirmed' && ! $this->allowed('confirm')) {
            return false;
        }
        foreach (is_array($this->input('items')) ? $this->input('items') : [] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (isset($item['unit_price']) && ! $this->allowed('price_override')) {
                return false;
            }
            if (isset($item['discount_amount']) && (! is_numeric($item['discount_amount']) || (float) $item['discount_amount'] !== 0.0) && ! $this->allowed('discount')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Adapt nested payload rules to the inputs rendered by the order form.
     *
     * @return array<string, array<int, mixed>>
     */
    public function clientRules(): array
    {
        $rules = $this->rules();
        unset($rules['items'], $rules['items.*'], $rules['billing'], $rules['shipping']);

        $rules['customer_name'][] = 'required_without:customer_id';
        $rules['customer_email'][] = 'required_without_all:customer_id,customer_phone';
        $rules['customer_phone'][] = 'required_without_all:customer_id,customer_email';

        foreach (['billing', 'shipping'] as $type) {
            foreach (['name', 'phone', 'address_line_1', 'city', 'postal_code', 'country_code', 'state_code'] as $field) {
                $rules[$type.'.'.$field][0] = 'required';
            }
        }

        foreach (['shipping_amount', 'items.*.unit_price', 'items.*.discount_amount'] as $field) {
            $rules[$field] = array_map(
                fn ($rule) => $rule === 'decimal:0,2' ? 'regex:/^\d+(?:\.\d{1,2})?$/' : $rule,
                $rules[$field],
            );
        }

        return $rules;
    }

    public function rules(): array
    {
        $partial = $this->route('order') !== null;
        $preview = $this->route()->getActionMethod() === 'preview';
        $required = $partial ? 'sometimes' : 'required';
        $money = ['numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'];
        $rules = [
            'coupon_code' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9_-]+$/'],
            'idempotency_key' => [$this->route()->getActionMethod() === 'store' ? 'required' : 'sometimes', 'string', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'customer_id' => ['nullable', 'integer', Rule::exists('tenant.customers', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'customer_name' => ['nullable', 'string', 'max:201'],
            'customer_email' => ['nullable', 'email', 'max:254'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'currency' => ['sometimes', Rule::in(['INR'])],
            'shipping_amount' => ['nullable', ...$money],
            'shipping_tax_id' => ['nullable', 'integer', Rule::exists('tenant.taxes', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'payment_method' => ['sometimes', Rule::in(['cod', 'cash', 'bank_transfer'])],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'internal_note' => ['nullable', 'string', 'max:5000'],
            'submit_as' => ['sometimes', Rule::in(['draft', 'pending', 'confirmed'])],
            'expected_pricing_fingerprint' => ['sometimes', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'items' => [$required, 'array', 'list', 'min:1', 'max:100'],
            'items.*' => ['array:product_id,product_variant_id,quantity,unit_price,discount_amount'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('tenant.products', 'id')->whereNull('deleted_at')->where('status', true)],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', Rule::exists('tenant.product_variants', 'id')->whereNull('deleted_at')->where('status', true)],
            'items.*.quantity' => ['required', 'integer', 'between:1,100000'],
            'items.*.unit_price' => ['nullable', ...$money],
            'items.*.discount_amount' => ['sometimes', ...$money],
            'same_as_billing' => ['sometimes', 'boolean'],
        ];
        foreach (['billing', 'shipping'] as $type) {
            $rules[$type] = [$type === 'shipping' || $partial || $preview ? 'sometimes' : 'required', 'array:name,phone,address_line_1,address_line_2,city,state_code,country_code,postal_code'];
            foreach (['name' => 200, 'phone' => 30, 'address_line_1' => 255, 'address_line_2' => 255, 'city' => 100, 'postal_code' => 20] as $field => $max) {
                $rules[$type.'.'.$field] = [$field === 'address_line_2' ? 'nullable' : 'required_with:'.$type, 'string', 'max:'.$max];
            }
            $rules[$type.'.country_code'] = ['required_with:'.$type, Rule::in(['IN'])];
            $rules[$type.'.state_code'] = ['required_with:'.$type, Rule::in(array_keys(config('customer_locations.IN.states')))];
        }
        if (
            $this->route()->getActionMethod() === 'store' && is_string($this->input('idempotency_key')) && DB::connection('tenant')->table('order_idempotency_keys')
                ->where('principal_id', $this->actor()->id)->where('operation', 'orders.create')->where('key', $this->input('idempotency_key'))->whereNotNull('response')->exists()
        ) {
            foreach (['customer_id', 'shipping_tax_id', 'items.*.product_id', 'items.*.product_variant_id'] as $field) {
                $rules[$field] = array_values(array_filter($rules[$field], fn ($rule) => ! $rule instanceof Exists));
            }
        }

        return $rules;
    }
}
