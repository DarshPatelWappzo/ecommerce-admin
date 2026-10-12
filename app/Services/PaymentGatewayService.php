<?php

namespace App\Services;

use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\OrderPaymentCheckout;
use App\Models\Tenant\PaymentGateway;
use App\Models\Tenant\User;
use App\Repositories\PaymentGatewayRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentGatewayService
{
    public function __construct(private readonly PaymentGatewayRepository $gateways, private readonly TenantAuditLogService $audit) {}

    /** @param array<string, mixed> $input Validated settings; blank credential values retain saved values. */
    public function save(array $input, ?User $actor = null): PaymentGateway
    {
        return DB::connection('tenant')->transaction(function () use ($input, $actor): PaymentGateway {
            $gateway = $this->gateways->lockForSave();
            $old = ['mode' => $gateway->mode, 'is_enabled' => $gateway->is_enabled];
            $versions = [];
            foreach (['test', 'live'] as $mode) {
                $field = $mode.'_credentials';
                $previous = $gateway->{$field} ?? [];
                $credentials = $previous;
                foreach (['key_id', 'key_secret', 'webhook_secret'] as $key) {
                    if (filled($input[$mode.'_'.$key] ?? null)) {
                        $credentials[$key] = $input[$mode.'_'.$key];
                    }
                }
                if ($input[$mode.'_remove_webhook_secret'] ?? false) {
                    unset($credentials['webhook_secret']);
                }
                if (($previous['key_id'] ?? null) && ($credentials['key_id'] ?? null) !== $previous['key_id'] && ! filled($input[$mode.'_key_secret'] ?? null)) {
                    throw ValidationException::withMessages([$mode.'_key_secret' => 'Enter the matching key secret when changing the Key ID.']);
                }
                if (filled($credentials['key_id'] ?? null) && ! preg_match('/^rzp_'.$mode.'_[A-Za-z0-9]+$/', $credentials['key_id'])) {
                    throw ValidationException::withMessages([$mode.'_key_id' => 'The Key ID must belong to this environment.']);
                }
                if ($credentials === []) {
                    continue;
                }
                $version = $this->gateways->current($gateway, $mode);
                if (! $version || $credentials !== $previous) {
                    if ($version) {
                        $version->update(['credentials' => $previous, 'is_current' => false]);
                    }
                    $version = $gateway->versions()->create(['mode' => $mode, 'key_id' => $credentials['key_id'] ?? null]);
                    $gateway->{$field} = $credentials;
                }
                $versions[$mode] = $version->id;
            }
            $gateway->mode = $input['mode'];
            $gateway->is_enabled = (bool) $input['is_enabled'];
            if ($gateway->is_enabled) {
                $credentials = $gateway->{$gateway->mode.'_credentials'} ?? [];
                foreach (['key_id', 'key_secret'] as $field) {
                    if (! filled($credentials[$field] ?? null)) {
                        throw ValidationException::withMessages([$gateway->mode.'_'.$field => 'Configure the active environment before enabling Razorpay.']);
                    }
                }
            }
            $this->gateways->save($gateway);
            $bound = 0;
            if ($input['bind_legacy'] ?? false) {
                $versionId = $versions[$gateway->mode] ?? null;
                if (! $versionId) {
                    throw ValidationException::withMessages(['bind_legacy' => 'Save the original merchant credentials before linking legacy records.']);
                }
                $credentials = $gateway->{$gateway->mode.'_credentials'};
                $this->validateCredentials($credentials, $gateway->mode);
                foreach ([OrderPaymentCheckout::class, OrderPayment::class] as $model) {
                    $bound += $model::where('gateway', 'razorpay')->where('gateway_account', $credentials['key_id'])
                        ->whereNull('payment_gateway_credential_id')->update(['payment_gateway_credential_id' => $versionId]);
                }
            }
            $this->audit->recordSnapshot($gateway, 'configuration_saved', $old, [
                'mode' => $gateway->mode, 'is_enabled' => $gateway->is_enabled, 'versions' => $versions, 'legacy_records_linked' => $bound,
            ], $actor);

            return $gateway;
        }, 3);
    }

    public function activeVersion(): int
    {
        try {
            return DB::connection('tenant')->transaction(function (): int {
                $gateway = $this->gateways->findByGateway(lock: true);
                if (! $gateway?->is_enabled) {
                    throw new OrderPaymentGatewayException;
                }
                $version = $this->gateways->current($gateway, $gateway->mode);
                if (! $version) {
                    throw new OrderPaymentGatewayException;
                }
                $this->validateCredentials($gateway->{$gateway->mode.'_credentials'} ?? [], $gateway->mode);

                return $version->id;
            });
        } catch (Throwable) {
            throw new OrderPaymentGatewayException;
        }
    }

    public function available(): bool
    {
        try {
            $this->activeVersion();

            return true;
        } catch (OrderPaymentGatewayException) {
            return false;
        }
    }

    /** @return array{key_id: string, key_secret: string, webhook_secret?: string} Backend-only values, never cached. */
    public function credentials(int $versionId): array
    {
        try {
            return DB::connection('tenant')->transaction(function () use ($versionId): array {
                $gateway = $this->gateways->findByGateway(lock: true);
                $version = $gateway?->versions()->find($versionId);
                if (! $version) {
                    throw new OrderPaymentGatewayException;
                }
                $credentials = $version->is_current ? $gateway->{$version->mode.'_credentials'} : $version->credentials;
                $this->validateCredentials($credentials ?? [], $version->mode);
                if ($credentials['key_id'] !== $version->key_id) {
                    throw new OrderPaymentGatewayException;
                }

                return $credentials;
            });
        } catch (Throwable) {
            throw new OrderPaymentGatewayException;
        }
    }

    public function versionFor(OrderPaymentCheckout|OrderPayment $payment): int
    {
        $id = $payment->payment_gateway_credential_id;
        if (! $id || $payment->gateway !== 'razorpay' || $this->credentials($id)['key_id'] !== $payment->gateway_account) {
            throw ValidationException::withMessages(['payment' => 'The original merchant credentials must be configured and linked to this payment before processing.']);
        }

        return $id;
    }

    /** @return array<string, mixed> Explicit browser-safe presentation, including only public Key IDs. */
    public function status(?int $tenantId): array
    {
        $gateway = $this->gateways->findByGateway();
        $data = ['mode' => $gateway?->mode ?? 'test', 'is_enabled' => $gateway?->is_enabled ?? false, 'environments' => [], 'webhooks' => []];
        foreach (['test', 'live'] as $mode) {
            $credentials = $gateway?->{$mode.'_credentials'} ?? [];
            $data['environments'][$mode] = [
                'key_id' => $credentials['key_id'] ?? '', 'key_secret_configured' => filled($credentials['key_secret'] ?? null),
                'webhook_secret_configured' => filled($credentials['webhook_secret'] ?? null),
            ];
        }
        if ($tenantId && $gateway) {
            foreach ($gateway->versions()->orderByDesc('id')->get() as $version) {
                $data['webhooks'][] = ['mode' => $version->mode, 'version' => $version->id, 'current' => $version->is_current,
                    'url' => route('api.tenant.payments.webhook', ['tenantId' => $tenantId, 'credentialId' => $version->id])];
            }
        }

        return $data;
    }

    public function savedVersion(string $mode): int
    {
        $gateway = $this->gateways->findByGateway();
        $version = $gateway ? $this->gateways->current($gateway, $mode) : null;
        if (! $version) {
            throw ValidationException::withMessages(['mode' => 'Save credentials for this environment before testing the connection.']);
        }
        $this->credentials($version->id);

        return $version->id;
    }

    public function retireUnusedVersion(int $id, User $actor): void
    {
        DB::connection('tenant')->transaction(function () use ($id, $actor): void {
            $gateway = $this->gateways->findByGateway(lock: true);
            $version = $gateway?->versions()->find($id);
            abort_unless($version, 404);
            if ($version->is_current) {
                throw ValidationException::withMessages(['version' => 'The current credential version cannot be removed.']);
            }
            foreach ([OrderPaymentCheckout::class, OrderPayment::class] as $model) {
                if ($model::where('gateway', 'razorpay')->where(function ($query) use ($id, $version): void {
                    $query->where('payment_gateway_credential_id', $id)
                        ->orWhere(function ($legacy) use ($version): void {
                            $legacy->whereNull('payment_gateway_credential_id')->where(function ($account) use ($version): void {
                                $account->where('gateway_account', $version->key_id)->orWhereNull('gateway_account')->orWhere('gateway_account', '');
                            });
                        });
                })->exists()) {
                    throw ValidationException::withMessages(['version' => 'Keep this version: historical or unassigned payments may still require it.']);
                }
            }
            $version->delete();
            $this->audit->recordSnapshot($gateway, 'unused_version_removed', null, ['version_id' => $id, 'mode' => $version->mode], $actor);
        }, 3);
    }

    /** @param array<string, mixed> $credentials Decrypted backend configuration. */
    private function validateCredentials(array $credentials, string $mode): void
    {
        if (! in_array($mode, ['test', 'live'], true) || ! preg_match('/^rzp_'.$mode.'_[A-Za-z0-9]+$/', $credentials['key_id'] ?? '') || ! filled($credentials['key_secret'] ?? null)) {
            throw new OrderPaymentGatewayException;
        }
    }
}
