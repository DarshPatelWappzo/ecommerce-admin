<?php

namespace App\Http\Requests;

use App\Models\Tenant\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Customer && $this->user()->status === 'active';
    }

    public function rules(): array
    {
        if ($this->route()->getActionMethod() === 'store') {
            return ['quantity' => ['required', 'integer', 'min:1'], 'reason_id' => ['required', 'integer', Rule::exists('tenant.return_reasons', 'id')->where('status', true)], 'reason_note' => ['nullable', 'string', 'max:2000'], 'amount' => ['prohibited'], 'refund_amount' => ['prohibited'], 'status' => ['prohibited'], 'customer_id' => ['prohibited']];
        }

        return ['per_page' => ['nullable', 'integer', 'between:1,100']];
    }
}
