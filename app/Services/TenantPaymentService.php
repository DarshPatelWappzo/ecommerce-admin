<?php

namespace App\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\OrderPaymentCheckout;
use App\Models\Tenant\OrderPaymentEvent;
use App\Models\Tenant\User;
use App\Repositories\TenantOrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantPaymentService
{
    public function __construct(
        private readonly OrderPaymentGateway $gateway,
        private readonly TenantOrderRepository $orders,
        private readonly TenantOrderService $orderService,
        private readonly TenantAuditLogService $audit,
        private readonly CustomerCartService $customerCarts,
    ) {}

    /** @return array<string, string> Enabled method labels, never credentials. */
    public static function methods(): array
    {
        $methods = [];
        foreach (config('order_payments.methods', []) as $name => $settings) {
            if ($settings['enabled']) {
                $methods[$name] = $settings['label'];
            }
        }

        return $methods;
    }

    public static function requireEnabled(string $method): void
    {
        if (! array_key_exists($method, self::methods())) {
            throw ValidationException::withMessages(['method' => 'This payment method is disabled.']);
        }
    }

    public static function minorUnits(string $amount): int
    {
        return TenantOrderCalculationService::money($amount)
            ->multipliedBy(100)
            ->toBigInteger()
            ->toInt();
    }

    /** Reserve one durable provider checkout per immutable submitted order. */
    public function initiate(int $id, User|Customer $actor, int $tenantDatabaseId): array
    {
        self::requireEnabled($this->gateway->name());
        $this->gateway->ensureConfigured();
        [$checkout, $create] = DB::connection('tenant')->transaction(function () use ($id, $actor): array {
            $order = $this->orders->find($id, true);
            $this->eligible($order);
            if ($this->orderService->captured($order)->isGreaterThan(0)) {
                throw ValidationException::withMessages(['order' => 'Online checkout requires an order with no collected payments.']);
            }
            $checkout = $order->paymentCheckout()->firstOrCreate([], [
                'reference' => (string) Str::uuid(),
                'gateway' => $this->gateway->name(),
                'gateway_account' => $this->gateway->publicKey(),
                'amount' => $order->grand_total,
                'currency' => $order->currency,
                'initiated_by' => ($actor instanceof User ? $actor->id : null),
            ]);
            $this->matchingAccount($checkout);
            $create = $checkout->requested_at === null;
            if ($create) {
                $checkout->update(['requested_at' => now()]);
            }
            $order->update(['payment_status' => 'pending']);

            return [$checkout, $create];
        });

        if (! $checkout->gateway_order_id) {
            $providerOrder = $create ? $this->gateway->createOrder([
                'receipt' => $checkout->reference,
                'amount' => self::minorUnits($checkout->amount),
                'currency' => $checkout->currency,
                'partial_payment' => false,
                'notes' => ['purpose' => 'tenant_order', 'tenant_database_id' => (string) $tenantDatabaseId, 'checkout_reference' => $checkout->reference],
            ]) : $this->gateway->findOrder($checkout->reference);
            if (! $providerOrder) {
                throw new OrderPaymentGatewayException;
            }
            $this->assertProviderOrder($checkout, $providerOrder, $tenantDatabaseId);
            DB::connection('tenant')->transaction(function () use ($checkout, $providerOrder): void {
                $order = $this->orders->find($checkout->order_id, true);
                $checkout->refresh();
                if ($checkout->gateway_order_id && $checkout->gateway_order_id !== $providerOrder['id']) {
                    throw new OrderPaymentGatewayException;
                }
                $checkout->update(['gateway_order_id' => $providerOrder['id']]);
                $order->payments()->whereNull('gateway_payment_id')->where('status', 'pending')->update([
                    'method' => $checkout->gateway,
                    'gateway' => $checkout->gateway,
                    'gateway_account' => $checkout->gateway_account,
                    'gateway_order_id' => $checkout->gateway_order_id,
                ]);
            });
        }

        return [
            'key' => $checkout->gateway_account,
            'order_id' => $checkout->gateway_order_id,
            'amount' => self::minorUnits($checkout->amount),
            'currency' => $checkout->currency,
            'local_order_id' => $checkout->order_id,
        ];
    }

    /** Verify the signature against the stored order identifier, then fetch provider truth. */
    public function verify(int $id, array $data, int $tenantDatabaseId): Order
    {
        $order = $this->orders->find($id);
        $checkout = $order->paymentCheckout()->firstOrFail();
        $this->matchingAccount($checkout);
        if (! $checkout->gateway_order_id || $checkout->gateway_order_id !== $data['razorpay_order_id']) {
            throw ValidationException::withMessages(['razorpay_order_id' => 'This checkout does not belong to the order.']);
        }
        $this->gateway->verifyPayment($checkout->gateway_order_id, $data['razorpay_payment_id'], $data['razorpay_signature']);
        $providerOrder = $this->gateway->fetchOrder($checkout->gateway_order_id);
        $payment = $this->gateway->fetchPayment($data['razorpay_payment_id']);
        if (($payment['id'] ?? null) !== $data['razorpay_payment_id']) {
            throw ValidationException::withMessages(['payment' => 'The provider payment does not match.']);
        }
        $this->reconcile($checkout, $providerOrder, $payment, $tenantDatabaseId);

        return $this->orders->details($id);
    }

    /** Recover provider facts without trusting a browser payment result or creating a new charge. */
    public function recover(int $id, int $tenantDatabaseId): Order
    {
        $order = $this->orders->find($id);
        $checkout = $order->paymentCheckout;
        if (! $checkout) {
            return $this->orders->details($id);
        }
        $this->matchingAccount($checkout);
        $providerOrder = $checkout->gateway_order_id
            ? $this->gateway->fetchOrder($checkout->gateway_order_id)
            : $this->gateway->findOrder($checkout->reference);
        if (! $providerOrder) {
            throw new OrderPaymentGatewayException;
        }
        $this->assertProviderOrder($checkout, $providerOrder, $tenantDatabaseId);
        DB::connection('tenant')->transaction(function () use ($checkout, $providerOrder, $tenantDatabaseId): void {
            $this->orders->find($checkout->order_id, true);
            $checkout->refresh();
            $this->assertProviderOrder($checkout, $providerOrder, $tenantDatabaseId);
            $checkout->update(['gateway_order_id' => $providerOrder['id']]);
        }, 3);
        foreach ($this->gateway->fetchOrderPayments($providerOrder['id']) as $payment) {
            if (! is_array($payment)) {
                throw new OrderPaymentGatewayException;
            }
            $this->reconcile($checkout, $providerOrder, $payment, $tenantDatabaseId);
        }

        return $this->orders->details($id);
    }

    /** @param array<string, mixed> $providerOrder */
    public function assertProviderOrder(OrderPaymentCheckout $checkout, array $providerOrder, int $tenantDatabaseId): void
    {
        $this->matchingAccount($checkout);
        if (
            ! is_string($providerOrder['id'] ?? null)
            || ! preg_match('/^order_[A-Za-z0-9]+$/', $providerOrder['id'])
            || ($checkout->gateway_order_id && $providerOrder['id'] !== $checkout->gateway_order_id)
            || ($providerOrder['receipt'] ?? null) !== $checkout->reference
            || ($providerOrder['amount'] ?? null) !== self::minorUnits($checkout->amount)
            || ($providerOrder['currency'] ?? null) !== $checkout->currency
            || (string) data_get($providerOrder, 'notes.tenant_database_id') !== (string) $tenantDatabaseId
            || data_get($providerOrder, 'notes.purpose') !== 'tenant_order'
            || data_get($providerOrder, 'notes.checkout_reference') !== $checkout->reference
        ) {
            throw ValidationException::withMessages(['payment' => 'The provider order does not match this tenant checkout.']);
        }
    }

    /** Apply callback/webhook facts under the same order lock as offline receipts. */
    public function reconcile(OrderPaymentCheckout $checkout, array $providerOrder, array $entity, int $tenantDatabaseId, ?array $event = null): void
    {
        $this->assertProviderOrder($checkout, $providerOrder, $tenantDatabaseId);
        if (($entity['order_id'] ?? null) !== $providerOrder['id']
            || ($entity['amount'] ?? null) !== self::minorUnits($checkout->amount)
            || ($entity['currency'] ?? null) !== $checkout->currency
            || ($entity['amount_refunded'] ?? 0) !== 0
            || ! is_string($entity['id'] ?? null)
            || ! preg_match('/^pay_[A-Za-z0-9]+$/', $entity['id'])
            || ! in_array($entity['status'] ?? null, ['created', 'captured', 'authorized', 'failed'], true)
            || ($entity['status'] === 'captured' && ($entity['captured'] ?? false) !== true)
        ) {
            throw ValidationException::withMessages(['payment' => 'The provider payment amount, currency or status does not match.']);
        }

        DB::connection('tenant')->transaction(function () use ($checkout, $providerOrder, $entity, $event, $tenantDatabaseId): void {
            $order = $this->orders->find($checkout->order_id, true);
            $checkout->refresh();
            $this->assertProviderOrder($checkout, $providerOrder, $tenantDatabaseId);
            if (
                $order->currency !== $checkout->currency
                || ! TenantOrderCalculationService::money($order->grand_total)->isEqualTo($checkout->amount)
            ) {
                throw ValidationException::withMessages(['order' => 'This order cannot accept this payment.']);
            }
            if ($event) {
                $existing = OrderPaymentEvent::where('event_id', $event['event_id'])->first();
                if ($existing) {
                    if ($existing->payload_hash !== $event['payload_hash'] || $existing->order_payment_checkout_id !== $checkout->id) {
                        throw ValidationException::withMessages(['event_id' => 'This webhook event ID has already been used for different content.'])->status(409);
                    }

                    return;
                }
            }
            $checkout->update(['gateway_order_id' => $providerOrder['id']]);
            $payment = OrderPayment::where('gateway', $checkout->gateway)->where('gateway_account', $checkout->gateway_account)
                ->where('gateway_payment_id', $entity['id'])->first();
            if ($payment && $payment->order_id !== $order->id) {
                throw ValidationException::withMessages(['payment' => 'The provider payment is already assigned to another order.']);
            }
            if ((! $payment || $payment->status !== 'captured')
                && ! ($payment && in_array($payment->status, ['authorized', 'failed'], true) && $entity['status'] === 'created')
            ) {
                $previousStatus = $payment?->status;
                $payment ??= $order->payments()->whereNull('gateway_payment_id')->where('status', 'pending')->first() ?? new OrderPayment(['order_id' => $order->id]);
                $payment->fill([
                    'method' => $checkout->gateway,
                    'gateway' => $checkout->gateway,
                    'gateway_account' => $checkout->gateway_account,
                    'gateway_order_id' => $providerOrder['id'],
                    'gateway_payment_id' => $entity['id'],
                    'reference_number' => $entity['id'],
                    'amount' => $checkout->amount,
                    'currency' => $checkout->currency,
                    'status' => $entity['status'],
                    'verified_at' => now(),
                    'recorded_by' => null,
                ]);
                if ($entity['status'] === 'captured') {
                    $payment->paid_at ??= now();
                    $payment->failure_code = null;
                    $payment->failure_message = null;
                    $needsReview = in_array($order->status, ['draft', 'cancelled'], true)
                        || $checkout->status === 'review'
                        || $this->orderService->captured($order)->plus($checkout->amount)->isGreaterThan($order->grand_total);
                    $checkout->update(['status' => $needsReview ? 'review' : 'paid']);
                } elseif ($entity['status'] === 'failed') {
                    $payment->failed_at ??= now();
                    $payment->failure_code = preg_replace('/[^A-Z0-9_]/', '', substr((string) ($entity['error_code'] ?? 'PAYMENT_FAILED'), 0, 100));
                    $payment->failure_message = 'The provider reported that this payment failed.';
                } elseif ($entity['status'] === 'authorized') {
                    $payment->authorized_at ??= now();
                }
                $payment->save();
                $received = $this->orderService->captured($order);
                $status = $received->isGreaterThanOrEqualTo($order->grand_total) ? 'paid'
                    : ($received->isGreaterThan(0) ? 'partially_paid'
                        : ($order->payments()->whereNotNull('gateway_payment_id')->whereIn('status', ['created', 'authorized'])->exists() ? 'pending' : 'failed'));
                $order->update(['payment_status' => $status]);
                if ($previousStatus !== $payment->status) {
                    $this->audit->recordSnapshot($payment, TenantAuditAction::GATEWAY_PAYMENT_RECONCILED, ['attempt_status' => $previousStatus], [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'payment_id' => $payment->id,
                        'attempt_status' => $payment->status,
                        'payment_status' => $status,
                        'checkout_status' => $checkout->status,
                    ]);
                }
            }
            if ($event) {
                $checkout->events()->create([...$event, 'gateway_payment_id' => $entity['id'], 'processed_at' => now()]);
            }
            DB::connection('tenant')->afterCommit(fn() => $this->customerCarts->completeOrder($order->id));
        }, 3);
    }

    public function collectCod(int $id, string $reference, User $actor): Order
    {
        self::requireEnabled('cod');

        return DB::connection('tenant')->transaction(function () use ($id, $reference, $actor): Order {
            $order = $this->orders->find($id, true);
            $this->eligible($order);
            if (! $order->payments()->where('method', 'cod')->whereNull('gateway')->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['order' => 'This is not an unpaid COD order.']);
            }
            $amount = TenantOrderCalculationService::money($order->grand_total)->minus($this->orderService->captured($order));

            return $this->orderService->payment($id, [
                'method' => 'cod',
                'reference_number' => $reference,
                'amount' => (string) $amount,
                'currency' => $order->currency,
            ], $actor);
        }, 3);
    }

    private function eligible(Order $order): void
    {
        if (
            in_array($order->status, ['draft', 'cancelled'], true) || $order->payment_status === 'paid'
            || $order->currency !== 'INR' || ! TenantOrderCalculationService::money($order->grand_total)->isGreaterThan(0)
        ) {
            throw ValidationException::withMessages(['order' => 'Only submitted, unpaid INR orders with a positive balance can accept payment.']);
        }
    }

    private function matchingAccount(OrderPaymentCheckout $checkout): void
    {
        if ($checkout->gateway !== $this->gateway->name() || $checkout->gateway_account !== $this->gateway->publicKey()) {
            throw ValidationException::withMessages(['payment' => 'The original provider account must be configured to reconcile this checkout.']);
        }
    }
}
