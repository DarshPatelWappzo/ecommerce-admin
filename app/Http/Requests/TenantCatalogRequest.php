<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TenantCatalogRequest extends TenantProductRequest
{
    public function rules(): array
    {
        $tag = $this->route()->getActionMethod() === 'saveTag';
        $table = $tag ? 'tags' : 'attributes';
        $id = (int) $this->input('id', 0);
        $rules = [
            'id' => ['nullable', 'integer', Rule::exists('tenant.' . $table, 'id')],
            'name' => ['required', 'string', 'max:191'],
            'status' => ['required', 'boolean'],
        ];
        if ($tag) {
            $rules['slug'] = ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenant.tags', 'slug')->ignore($id)];
        } else {
            $rules['code'] = ['required', 'string', 'max:191', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('tenant.attributes', 'code')->ignore($id)];
            $rules['type'] = ['required', Rule::in(['text', 'number', 'boolean', 'date', 'select'])];
            $rules['is_filterable'] = ['required', 'boolean'];
            $rules['is_variant'] = ['required', 'boolean'];
            $rules['options'] = ['present', 'array', 'max:100'];
            $rules['options.*'] = ['array:id,value,label,sort_order'];
            $rules['options.*.id'] = ['nullable', 'integer', 'distinct', Rule::exists('tenant.attribute_options', 'id')->where('attribute_id', $id)];
            $rules['options.*.value'] = ['required', 'string', 'max:191', 'distinct:ignore_case'];
            $rules['options.*.label'] = ['required', 'string', 'max:191'];
            $rules['options.*.sort_order'] = ['required', 'integer', 'min:0', 'max:2147483647'];
        }

        return $rules;
    }
}
