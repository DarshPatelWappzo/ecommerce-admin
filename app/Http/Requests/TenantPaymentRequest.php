<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantPaymentRequest extends FormRequest
{
    public function actor(): User
    {
        return $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
    }

    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (! $user instanceof User || $user->status !== 'active' || $user->is_first_login) {
            return false;
        }
        $permission = match ($this->route()->getActionMethod()) {
            'initiate', 'verify', 'collect', 'reconcile' => 'orders.payments',
            'status', 'methods' => 'orders.view',
            default => 'payments.view',
        };

        return app(TenantRoleRepository::class)->userHasPermission($user, $permission);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reference_number'))) {
            $this->merge(['reference_number' => strtoupper(trim($this->input('reference_number')))]);
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'verify' => [
                'razorpay_order_id' => ['required', 'string', 'max:100', 'regex:/^order_[A-Za-z0-9]+$/'],
                'razorpay_payment_id' => ['required', 'string', 'max:100', 'regex:/^pay_[A-Za-z0-9]+$/'],
                'razorpay_signature' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            ],
            'collect' => ['reference_number' => ['required', 'string', 'max:100']],
            'index' => [
                'search' => ['nullable', 'string', 'max:200'],
                'method' => ['nullable', Rule::in(['cod', 'cash', 'bank_transfer', 'razorpay'])],
                'status' => ['nullable', Rule::in(['created', 'pending', 'authorized', 'captured', 'failed', 'superseded'])],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
                'per_page' => ['nullable', 'integer', 'between:1,100'],
            ],
            default => [],
        };
    }
}
