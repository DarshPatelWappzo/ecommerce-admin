<?php

namespace App\Http\Requests;

use App\Models\Tenant\Tax;
use Illuminate\Validation\Rule;

class TenantTaxSaveRequest extends TenantTaxRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $tax = $this->route('tax');
        $uniqueCode = Rule::unique('tenant.taxes', 'code');
        if ($tax instanceof Tax) {
            $uniqueCode->ignore($tax);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', $uniqueCode],
            'rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'tax name', 'code' => 'tax code', 'rate' => 'rate', 'is_active' => 'status'];
    }
}
