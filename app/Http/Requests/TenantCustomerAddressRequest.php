<?php

namespace App\Http\Requests;

class TenantCustomerAddressRequest extends TenantCustomerRequest
{
    protected function permission(): string
    {
        return $this->route()->getActionMethod() === 'index' ? 'customers.view' : 'customers.addresses';
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'defaults') {
            return [
                'is_default_shipping' => ['required_without:is_default_billing', 'boolean'],
                'is_default_billing' => ['required_without:is_default_shipping', 'boolean'],
            ];
        }

        return [];
    }
}
