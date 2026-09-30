<?php

namespace App\Http\Requests;

use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantOrderRequest extends FormRequest
{
    public function actor(): User
    {
        return $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
    }

    public function allowed(string $action): bool
    {
        return app(TenantRoleRepository::class)->userHasPermission($this->actor(), 'orders.' . $action);
    }

    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (! $user instanceof User || $user->status !== 'active' || $user->is_first_login) {
            return false;
        }
        $action = match ($this->route()->getActionMethod()) {
            'store', 'create' => 'create',
            'update', 'edit', 'removeCoupon' => 'update',
            'confirm', 'process', 'cancel', 'payments', 'ship', 'deliver' => $this->route()->getActionMethod(),
            'preview', 'options' => $this->allowed('create') ? 'create' : 'update',
            default => 'view',
        };

        return $this->allowed($action);
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::in(array_keys(Order::TRANSITIONS))],
            'payment_status' => ['nullable', Rule::in(['unpaid', 'pending', 'failed', 'partially_paid', 'paid'])],
            'payment_method' => ['nullable', Rule::in(['cod', 'cash', 'bank_transfer', 'online', 'razorpay'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'kind' => ['nullable', Rule::in(['customers', 'variants', 'taxes'])],
        ];
    }
}
