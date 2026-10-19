<?php

namespace App\Http\Requests;

use App\Services\PackagePricingService;
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
        $rules = [
            'grace_period_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:365'],
            'is_tenant_visible' => ['sometimes', 'boolean'],
            'public_infrastructure' => ['sometimes', 'boolean'],
            'subscription_currency' => ['sometimes', 'required', Rule::in([config('subscriptions.currency')])],
            'subscription_prices' => ['sometimes', 'array:monthly,quarterly,yearly'],
            'package_limits' => ['sometimes', 'array:max_monthly_orders,max_admin_users,audit_retention_days'],
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
        foreach (PackagePricingService::CYCLES as $cycle => $months) {
            $rules["subscription_prices.$cycle"] = ['sometimes', 'array:price,is_active'];
            $rules["subscription_prices.$cycle.is_active"] = ["required_with:subscription_prices.$cycle", 'boolean'];
            $rules["subscription_prices.$cycle.price"] = ["required_if:subscription_prices.$cycle.is_active,1,true", 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'];
        }
        foreach (PackagePricingService::LIMITS as $key => $label) {
            $rules["package_limits.$key"] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'];
        }

        return $rules;
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
