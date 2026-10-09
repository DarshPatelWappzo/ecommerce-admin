<?php

namespace App\Http\Requests;

class CustomerWishlistRequest extends CustomerShoppingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->route()->getActionMethod() === 'store') {
            $rules['product_id'] = ['required', 'integer', 'min:1'];
        }
        if ($this->route()->getActionMethod() === 'move') {
            $rules += ['product_variant_id' => ['nullable', 'integer', 'min:1'], 'quantity' => ['required', 'integer', 'between:1,100000']];
        }

        return $rules;
    }
}
