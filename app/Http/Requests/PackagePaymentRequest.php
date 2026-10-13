<?php

namespace App\Http\Requests;

use App\Models\Tenant\User;
use App\Models\TenantDatabase;
use App\Repositories\TenantRoleRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PackagePaymentRequest extends FormRequest
{
    public function actor(): ?User
    {
        $actor = $this->user($this->is('api/*') ? 'sanctum' : 'tenant');

        return $actor instanceof User ? $actor : null;
    }

    public function authorize(): bool
    {
        $actor = $this->actor();
        if (! $actor || $actor->status !== 'active' || $actor->is_first_login) {
            return false;
        }

        return app(TenantRoleRepository::class)->userHasPermission($actor, 'plans.view')
            && $actor->roles()->where('slug', 'admin')->where('status', true)->exists();
    }

    public function tenant(): TenantDatabase
    {
        $id = $this->is('api/*') ? $this->actor()?->currentAccessToken()?->tenant_database_id : $this->attributes->get('tenant_database')?->id;

        return TenantDatabase::on(config('subscriptions.connection'))->findOrFail($id);
    }

    protected function prepareForValidation(): void
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function rules(): array
    {
        return match ($this->route()->getActionMethod()) {
            'checkout' => [
                'billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
                'idempotency_key' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
                'amount' => ['prohibited'],
                'actual_user' => ['prohibited'],
                'capacity' => ['prohibited'],
                'tenant_id' => ['prohibited']
            ],
            'verify' => [
                'razorpay_order_id' => ['required', 'string', 'max:100', 'regex:/^order_[A-Za-z0-9]+$/'],
                'razorpay_payment_id' => ['required', 'string', 'max:100', 'regex:/^pay_[A-Za-z0-9]+$/'],
                'razorpay_signature' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            ],
            default => ['page' => ['nullable', 'integer', 'min:1']],
        };
    }
}
