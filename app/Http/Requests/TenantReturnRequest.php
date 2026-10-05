<?php

namespace App\Http\Requests;

use App\Models\Tenant\ReturnRequest;
use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantReturnRequest extends FormRequest
{
    public function actor(): User
    {
        return $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
    }

    public function allowed(string $permission): bool
    {
        return app(TenantRoleRepository::class)->userHasPermission($this->actor(), $permission);
    }

    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (! $user instanceof User || $user->status !== 'active' || $user->is_first_login) {
            return false;
        }
        $permission = match ($this->route()->getActionMethod()) {
            'initiate' => 'refunds.initiate',
            'retry' => 'refunds.retry',
            'reconcile' => 'refunds.reconcile',
            'manual' => 'refunds.manual',
            'reasons', 'saveReason' => 'returns.reasons',
            'transition' => match ($this->input('status')) {
                'approved', 'rejected', 'cancelled' => 'returns.review',
                'received', 'in_transit' => 'returns.receive',
                'inspection_passed', 'inspection_failed' => 'returns.inspect',
                'closed' => 'returns.close',
                default => 'returns.view',
            },
            default => 'returns.view',
        };

        return $this->allowed($permission);
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string', 'max:200'], 'order_number' => ['nullable', 'string', 'max:40'], 'customer_id' => ['nullable', 'integer', 'min:1'], 'status' => ['nullable', Rule::in(array_keys(ReturnRequest::TRANSITIONS))], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])], 'per_page' => ['nullable', 'integer', 'between:1,100']];
    }
}
