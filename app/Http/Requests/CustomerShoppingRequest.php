<?php

namespace App\Http\Requests;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;

class CustomerShoppingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer && $this->user()->status === 'active';
    }

    public function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            ...array_fill_keys(['customer_id', 'tenant_id', 'price', 'unit_price', 'tax', 'discount', 'total', 'grand_total', 'payment_status', 'internal_note', 'shipping_amount', 'shipping_tax_id'], ['prohibited']),
        ];
    }
}
