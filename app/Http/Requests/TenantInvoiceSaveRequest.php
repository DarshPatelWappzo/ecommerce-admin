<?php

namespace App\Http\Requests;

class TenantInvoiceSaveRequest extends TenantInvoiceRequest
{
    public static function formRules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'min:1'],
            'invoice_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'customer_name' => ['sometimes', 'required', 'string', 'max:201'],
            'gstin' => ['nullable', 'string', 'max:15'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'billing' => ['sometimes', 'array:name,phone,address_line_1,address_line_2,city,state_code,country_code,postal_code'],
            'billing.name' => ['sometimes', 'required', 'string', 'max:200'],
            'billing.phone' => ['sometimes', 'required', 'string', 'max:30'],
            'billing.address_line_1' => ['sometimes', 'required', 'string', 'max:255'],
            'billing.address_line_2' => ['nullable', 'string', 'max:255'],
            'billing.city' => ['sometimes', 'required', 'string', 'max:100'],
            'billing.state_code' => ['sometimes', 'required', 'string', 'max:10'],
            'billing.country_code' => ['sometimes', 'required', 'string', 'size:2'],
            'billing.postal_code' => ['sometimes', 'required', 'string', 'max:20'],
        ];
    }

    public function rules(): array
    {
        return self::formRules();
    }
}
