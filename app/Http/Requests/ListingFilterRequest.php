<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListingFilterRequest extends FormRequest
{
    /** Authorization remains in the existing route middleware and controllers. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> Validated filters for administration lists. */
    public function rules(): array
    {
        $booleanStatus = $this->routeIs('tenant.categories.index', 'tenant.roles.index');
        $rules = [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', $booleanStatus ? 'boolean' : Rule::in(['active', 'inactive'])],
        ];

        if ($this->routeIs('tenant.categories.index')) {
            $rules['parent_id'] = ['nullable', 'integer', 'min:1'];
            $rules['from'] = ['nullable', 'date_format:Y-m-d'];
            $rules['to'] = ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])];
        }

        return $rules;
    }
}
