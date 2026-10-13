<?php

namespace App\Services;

use App\Exceptions\PlatformPaymentException;
use App\Models\PackagePayment;
use App\Models\PlatformGatewayVersion;
use App\Models\PlatformWebhookEvent;
use App\Models\TenantDatabase;
use App\Repositories\PlatformPaymentRepository;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PackageCheckoutService
{
    public function __construct(
        private readonly PlatformPaymentRepository $payments,
        private readonly PlatformGatewayConfigurationService $settings,
        private readonly PlatformPaymentGatewayManager $gateways,
        private readonly PackagePricingService $pricing,
        private readonly TenantSubscriptionService $subscriptions,
    ) {}

    public function checkout(TenantDatabase $tenant, int $packageId, string $cycle, string $key): PackagePayment
    {
        $hash = hash('sha256', json_encode(['package_id' => $packageId, 'billing_cycle' => $cycle], JSON_THROW_ON_ERROR));
        $created = false;
        $payment = $this->payments->connection()->transaction(function () use ($tenant, $packageId, $cycle, $key, $hash, &$created): PackagePayment {
            $created = false;
            $this->payments->lockOwner($tenant);
            $existing = PackagePayment::query()
                ->where('tenant_database_id', $tenant->id)
                ->where('idempotency_key', $key)
                ->first();
            if ($existing) {
                if ($existing->payload_hash !== $hash) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different purchase.']);
                }

                return $existing;
            }
            $version = $this->settings->active();
            if (PackagePayment::query()->where('owner_id', $tenant->user_id)->where('mode', $version->mode)
                ->whereNotIn('status', ['captured', 'rejected'])->exists()
            ) {
                throw ValidationException::withMessages(['payment' => 'Resume the existing pending payment from payment history before starting another purchase.']);
            }
            $this->subscriptions->assertPurchasable($tenant, $packageId, $version->mode);
            $quote = $this->pricing->quote($packageId, $cycle);
            $created = true;

            return PackagePayment::create([
                'tenant_database_id' => $tenant->id,
                'owner_id' => $tenant->user_id,
                'package_id' => $packageId,
                'quote' => $quote,
                'billing_cycle' => $cycle,
                'amount' => $quote['amount'],
                'currency' => $quote['currency'],
                'gateway' => 'razorpay',
                'mode' => $version->mode,
                'configuration_version_id' => $version->id,
                'reference' => (string) Str::uuid(),
                'idempotency_key' => $key,
                'payload_hash' => $hash,
            ]);
        }, 3);
        if (! $created) {
            return $payment;
        }
        $version = PlatformGatewayVersion::findOrFail($payment->configuration_version_id);
        try {
            $order = $this->gateways->resolve($payment->gateway)->createOrder($payment, $version);
            $this->validateOrder($payment, $order);
            PackagePayment::query()->whereKey($payment->id)->whereNull('gateway_order_id')
                ->update(['gateway_order_id' => $order['id'], 'status' => 'pending']);
        } catch (PlatformPaymentException $exception) {
            PackagePayment::query()->whereKey($payment->id)->where('status', 'creating')
                ->update(['status' => $exception->creationRejected ? 'rejected' : 'creating', 'failure_code' => $exception->creationRejected ? 'order_rejected' : 'confirmation_pending']);
        }

        return $payment->refresh();
    }

    /** @param array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: string} $input */
    public function verify(PackagePayment $payment, array $input): PackagePayment
    {
        if (! $payment->gateway_order_id || $payment->gateway_order_id !== $input['razorpay_order_id']) {
            throw ValidationException::withMessages(['razorpay_order_id' => 'The order does not match this checkout.']);
        }
        $version = PlatformGatewayVersion::findOrFail($payment->configuration_version_id);
        $this->gateways->resolve($payment->gateway)->verifySignature($payment, $version, $input['razorpay_payment_id'], $input['razorpay_signature']);

        return $this->reconcile($payment, $input['razorpay_payment_id']);
    }

    public function reconcile(PackagePayment $payment, ?string $paymentId = null): PackagePayment
    {
        if ($payment->status === 'captured' || $payment->status === 'rejected') {
            return $payment;
        }
        $version = PlatformGatewayVersion::findOrFail($payment->configuration_version_id);
        $gateway = $this->gateways->resolve($payment->gateway);
        if (! $payment->gateway_order_id) {
            $order = $gateway->findOrder($payment, $version);
            if (! $order) {
                return $payment;
            }
            $this->validateOrder($payment, $order);
            PackagePayment::query()->whereKey($payment->id)->whereNull('gateway_order_id')
                ->update(['gateway_order_id' => $order['id'], 'status' => 'pending']);
            $payment->refresh();
        }
        $status = $gateway->fetchStatus($payment, $version, $paymentId);
        $this->validateStatus($payment, $status);

        return $this->commitStatus($payment, $status);
    }

    /** @param array{order: array<string, mixed>, payments: list<array<string, mixed>>} $status */
    private function validateStatus(PackagePayment $payment, array $status): void
    {
        $this->validateOrder($payment, $status['order']);
        foreach ($status['payments'] as $providerPayment) {
            if (
                ! is_array($providerPayment) || ! is_string($providerPayment['id'] ?? null)
                || ! preg_match('/^pay_[A-Za-z0-9]+$/', $providerPayment['id'])
                || ($providerPayment['order_id'] ?? null) !== $payment->gateway_order_id
                || ($providerPayment['amount'] ?? null) !== $payment->amount
                || ($providerPayment['currency'] ?? null) !== $payment->currency
                || (isset($providerPayment['live']) && (bool) $providerPayment['live'] !== ($payment->mode === 'live'))
            ) {
                throw new PlatformPaymentException;
            }
        }
    }

    /** @param array<string, mixed> $order */
    private function validateOrder(PackagePayment $payment, array $order): void
    {
        if (
            ! is_string($order['id'] ?? null) || ! preg_match('/^order_[A-Za-z0-9]+$/', $order['id'])
            || ($payment->gateway_order_id && $order['id'] !== $payment->gateway_order_id)
            || ($order['amount'] ?? null) !== $payment->amount || ($order['currency'] ?? null) !== $payment->currency
            || ($order['receipt'] ?? null) !== $payment->reference
            || ($order['notes']['purpose'] ?? null) !== 'platform_package'
            || ($order['notes']['reference'] ?? null) !== $payment->reference
            || ($order['notes']['mode'] ?? null) !== $payment->mode
            || (string) ($order['notes']['tenant_database_id'] ?? '') !== (string) $payment->tenant_database_id
        ) {
            throw new PlatformPaymentException;
        }
    }

    /** @param array{order: array<string, mixed>, payments: list<array<string, mixed>>} $status */
    private function commitStatus(PackagePayment $payment, array $status): PackagePayment
    {
        return $this->payments->connection()->transaction(function () use ($payment, $status): PackagePayment {
            $tenant = TenantDatabase::on(config('subscriptions.connection'))->findOrFail($payment->tenant_database_id);
            $this->payments->lockOwner($tenant);
            $locked = PackagePayment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'captured') {
                return $locked;
            }
            foreach ($status['payments'] as $providerPayment) {
                if (($providerPayment['status'] ?? null) === 'captured' && ($providerPayment['captured'] ?? false) === true) {
                    $locked->update(['status' => 'captured', 'gateway_payment_id' => $providerPayment['id'], 'paid_at' => now(), 'failure_code' => null]);
                    $subscription = $this->subscriptions->activatePayment($tenant, $locked);
                    $locked->update(['subscription_id' => $subscription->id]);

                    return $locked;
                }
            }
            $states = array_column($status['payments'], 'status');
            $locked->update(['status' => in_array('authorized', $states, true) ? 'authorized' : (in_array('failed', $states, true) ? 'failed' : 'pending')]);

            return $locked;
        }, 3);
    }

    public function webhook(int $versionId, string $eventId, string $raw, string $signature): void
    {
        $version = PlatformGatewayVersion::findOrFail($versionId);
        $event = $this->gateways->resolve('razorpay')->webhook($raw, $signature, $version);
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,100}$/', $eventId), 422, 'Missing event identifier.');
        $hash = hash('sha256', $raw);
        $existing = PlatformWebhookEvent::query()->where('configuration_version_id', $versionId)->where('event_id', $eventId)->first();
        if ($existing) {
            abort_unless($existing->body_hash === $hash, 409, 'Event identifier conflict.');

            return;
        }
        $payment = null;
        $status = null;
        if (in_array($event['type'], ['payment.captured', 'order.paid', 'payment.failed'], true)) {
            $payment = PackagePayment::query()->where('configuration_version_id', $versionId)->where('gateway_order_id', $event['order_id'])->first();
            if (! $payment) {
                throw new PlatformPaymentException;
            }
            if (($event['amount'] !== null && $event['amount'] !== $payment->amount) || ($event['currency'] !== null && $event['currency'] !== $payment->currency)) {
                throw new PlatformPaymentException;
            }
            $status = $this->gateways->resolve($payment->gateway)->fetchStatus($payment, $version, $event['payment_id']);
            $this->validateStatus($payment, $status);
        }
        $this->payments->connection()->transaction(function () use ($versionId, $eventId, $hash, $event, $payment, $status): void {
            PlatformGatewayVersion::query()->whereKey($versionId)->lockForUpdate()->firstOrFail();
            $existing = PlatformWebhookEvent::query()->where('configuration_version_id', $versionId)->where('event_id', $eventId)->first();
            if ($existing) {
                abort_unless($existing->body_hash === $hash, 409);

                return;
            }
            if ($payment && $status) {
                $this->commitStatus($payment, $status);
            }
            PlatformWebhookEvent::create(['configuration_version_id' => $versionId, 'event_id' => $eventId, 'body_hash' => $hash, 'event_type' => $event['type'], 'payment_id' => $payment?->id]);
        }, 3);
    }

    /** @return array<string, mixed> */
    public function response(PackagePayment $payment): array
    {
        $data = $payment->toArray();
        $data['checkout'] = null;
        if ($payment->gateway_order_id && ! in_array($payment->status, ['captured', 'rejected'], true)) {
            $version = PlatformGatewayVersion::findOrFail($payment->configuration_version_id);
            $data['checkout'] = ['key' => $version->credentials['key_id'], 'order_id' => $payment->gateway_order_id, 'amount' => $payment->amount, 'currency' => $payment->currency, 'name' => $payment->quote['name']];
        }

        return $data;
    }
}
