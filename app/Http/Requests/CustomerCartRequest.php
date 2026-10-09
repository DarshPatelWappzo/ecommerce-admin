<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerCartRequest extends CustomerShoppingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->route()->getActionMethod() === 'store') {
            $rules += ['product_id' => ['required', 'integer', 'min:1'], 'product_variant_id' => ['nullable', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'between:1,100000']];
        }
        if ($this->route()->getActionMethod() === 'update') {
            $rules += ['quantity' => ['required', 'integer', 'between:1,100000'], 'operation' => ['sometimes', Rule::in(['set', 'increase', 'decrease'])]];
        }

        return $rules;
    }
}
