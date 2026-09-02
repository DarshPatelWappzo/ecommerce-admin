<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SuperAdminPackageStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('packages', 'slug')->ignore($this->route('package'))],
            'min_monthly_users' => ['required', 'integer', 'min:0'],
            'max_monthly_users' => ['nullable', 'integer', 'gte:min_monthly_users'],
            'cpu_vcores' => ['nullable', 'numeric', 'min:0'],
            'ram_gb' => ['nullable', 'numeric', 'min:0'],
            'application_servers' => ['required', 'integer', 'min:1'],
            'database_type' => ['nullable', 'string', 'max:100'],
            'infrastructure_summary' => ['required', 'string'],
            'min_monthly_cost' => ['required', 'numeric', 'min:0'],
            'max_monthly_cost' => ['required', 'numeric', 'gte:min_monthly_cost'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_period' => ['required', Rule::in(['monthly', 'yearly'])],
            'bandwidth_gb' => ['nullable', 'numeric', 'min:0'],
            'storage_gb' => ['nullable', 'numeric', 'min:0'],
            'backup_included' => ['boolean'],
            'cdn_included' => ['boolean'],
            'load_balancer_included' => ['boolean'],
            'description' => ['nullable', 'string'],
            'cost_disclaimer' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_recommended' => ['boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required.',
            'name.string' => 'Enter a valid package name.',
            'name.max' => 'Package name is too long.',
            'status.required' => 'Select a status.',
            'status.in' => 'Select a valid status.',
        ];
    }
}
