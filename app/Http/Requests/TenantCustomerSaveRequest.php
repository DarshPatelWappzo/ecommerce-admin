<?php

namespace App\Http\Requests;

use App\Repositories\TenantRoleRepository;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TenantCustomerSaveRequest extends TenantCustomerRequest
{
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        return $this->input('address') === null || app(TenantRoleRepository::class)->userHasPermission(
            $this->user($this->is('api/*') ? 'sanctum' : 'tenant'),
            'customers.addresses'
        );
    }

    protected function prepareForValidation(): void
    {
        $input = TenantCustomerAddressSaveRequest::normalize($this->all());
        if (($input['customer_type'] ?? null) === 'individual') {
            $input['company_name'] = null;
            $input['gstin'] = null;
        }
        if (isset($input['address']) && is_array($input['address'])) {
            $input['address'] = TenantCustomerAddressSaveRequest::normalize($input['address']);
        }
        $this->merge($input);
    }

    public function rules(): array
    {
        $customer = $this->customer();
        $presence = $customer ? 'sometimes' : 'required';
        $rules = [
            'first_name' => [$presence, 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'string', 'email', 'max:254', Rule::unique('tenant.customers', 'email')->ignore($customer)],
            'phone_country_code' => ['nullable', 'string', 'max:5', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{4,20}$/'],
            'customer_type' => ['sometimes', Rule::in(['individual', 'business'])],
            'company_name' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
        if (! $customer) {
            $rules['email'][] = 'required_without:phone';
            $rules['phone'][] = 'required_without:email';
            $rules['phone_country_code'][] = 'required_with:phone';
            $rules['company_name'][] = 'required_if:customer_type,business';
            $rules['address'] = ['sometimes', 'nullable', 'array:label,recipient_name,phone_country_code,phone,address_line_1,address_line_2,landmark,city,state_code,country_code,postal_code,is_default_shipping,is_default_billing'];
            if ($this->input('address') !== null) {
                $country = $this->input('address.country_code');
                foreach (TenantCustomerAddressSaveRequest::addressRules(is_string($country) ? $country : null) as $key => $rule) {
                    $rules['address.' . $key] = $rule;
                }
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return ['email.unique' => 'This email is already used by a customer, including archived customers.', 'gstin.regex' => 'Enter a GSTIN in the standard 15-character format.'];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $customer = $this->customer();
            $value = fn(string $field): mixed => $this->exists($field) ? $this->input($field) : $customer?->$field;
            if (! $value('email') && ! $value('phone')) {
                $validator->errors()->add('email', 'Provide an email address or mobile number.');
            }
            if ($value('phone') && ! $value('phone_country_code')) {
                $validator->errors()->add('phone_country_code', 'A calling code is required with a mobile number.');
            }
            if ($value('customer_type') === 'business' && ! $value('company_name')) {
                $validator->errors()->add('company_name', 'Registered business name is required for Business customers.');
            }
        }];
    }
}
