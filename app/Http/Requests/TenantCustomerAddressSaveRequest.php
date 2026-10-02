<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TenantCustomerAddressSaveRequest extends TenantCustomerAddressRequest
{
    public static function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if (in_array($key, ['phone'], true)) {
                    $value = preg_replace('/[\s().-]+/', '', $value);
                }
                if ($key === 'phone_country_code' && preg_match('/^\+?[1-9][0-9]{0,3}$/', $value)) {
                    $value = '+' . ltrim($value, '+');
                }
                if (in_array($key, ['country_code', 'state_code', 'gstin'], true)) {
                    $value = strtoupper($value);
                }
                if ($key === 'email') {
                    $value = strtolower($value);
                }
                $input[$key] = $value === '' ? null : $value;
            }
        }

        return $input;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(self::normalize($this->all()));
    }

    public static function addressRules(?string $country, bool $partial = false): array
    {
        $required = $partial ? ['sometimes', 'required'] : ['required'];

        return [
            'label' => ['nullable', 'string', 'max:50'],
            'recipient_name' => [...$required, 'string', 'max:200'],
            'phone_country_code' => [...$required, 'string', 'max:5', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'phone' => [...$required, 'string', 'regex:/^[0-9]{4,20}$/'],
            'address_line_1' => [...$required, 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'city' => [...$required, 'string', 'max:100'],
            'country_code' => [...$required, 'string', 'size:2', Rule::in(array_keys(config('customer_locations')))],
            'state_code' => [...$required, 'string', 'max:10', Rule::in(array_keys(config('customer_locations.' . $country . '.states', [])))],
            'postal_code' => [...$required, 'string', 'max:20'],
            'is_default_shipping' => ['sometimes', 'boolean'],
            'is_default_billing' => ['sometimes', 'boolean'],
        ];
    }

    public function rules(): array
    {
        $address = $this->route('address') === null ? null : $this->customer()->addresses()->findOrFail($this->route('address'));
        $country = $this->input('country_code', $address?->country_code ?? 'IN');
        $rules = self::addressRules(is_string($country) ? $country : null, $address !== null);
        if ($address && $this->exists('country_code') && ! $this->exists('state_code')) {
            $rules['country_code'][] = function (string $attribute, mixed $value, \Closure $fail) use ($address): void {
                if (! is_string($value)) {
                    return;
                }
                if (! array_key_exists($address->state_code, config('customer_locations.' . $value . '.states', []))) {
                    $fail('Select a state belonging to the selected country.');
                }
            };
        }

        return $rules;
    }
}
