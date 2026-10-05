<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantInvoiceRequest extends FormRequest
{
    public function actor(): User
    {
        return $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
    }

    public function allowed(string $action): bool
    {
        return app(TenantRoleRepository::class)->userHasPermission($this->actor(), 'invoices.' . $action);
    }

    public function authorize(): bool
    {
        $user = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');
        if (! $user instanceof User || $user->status !== 'active' || $user->is_first_login) {
            return false;
        }

        return $this->allowed(match ($this->route()->getActionMethod()) {
            'create', 'store' => 'create',
            'edit', 'update' => 'update',
            'issue' => 'issue',
            'pdf', 'print' => 'download',
            default => 'view',
        });
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'number' => ['nullable', 'string', 'max:16'],
            'customer_name' => ['nullable', 'string', 'max:201'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'issued'])],
            'mode' => ['nullable', Rule::in(['automatic', 'manual'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
