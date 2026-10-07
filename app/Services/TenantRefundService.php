<?php

namespace App\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\Refund;
use App\Models\Tenant\RefundAttempt;
use App\Models\Tenant\ReturnRequest;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantRefundService
{
    public function __construct(private readonly RazorpayRefundGateway $gateway, private readonly TenantAuditLogService $audit) {}

    /** Reserve the immutable item amount under the order lock before contacting the gateway. */
    public function initiate(int $returnId, User $actor, bool $retry = false): Refund
    {
        $dispatch = false;
        $refund = DB::connection('tenant')->transaction(function () use ($returnId, $actor, $retry, &$dispatch): Refund {
            $orderId = ReturnRequest::findOrFail($returnId)->order_id;
            $order = Order::lockForUpdate()->findOrFail($orderId);
            if ($order->paymentCheckout()->where('status', 'review')->exists()) {
                throw ValidationException::withMessages(['refund' => 'Resolve the payment review before initiating a refund.']);
            }
            $return = ReturnRequest::lockForUpdate()->findOrFail($returnId);
            $existing = $return->refund()->first();
            if ($existing && ! $retry) {
                return $existing;
            }
            if ($retry) {
                if (! $existing || $existing->gateway !== 'razorpay' || $existing->status !== 'failed' || $return->status !== 'refund_failed') {
                    throw ValidationException::withMessages(['refund' => 'Only a verified failed gateway refund can be retried. Reconcile uncertain refunds first.']);
                }
                $refund = $existing;
            } else {
                if (! in_array($return->status, ['inspection_passed', 'refund_pending'], true)) {
                    throw ValidationException::withMessages(['refund' => 'Pass inspection before initiating a refund.']);
                }
                $amount = TenantOrderCalculationService::money($return->refund_amount);
                $reserved = TenantOrderCalculationService::money('0');
                foreach (Refund::where('order_id', $order->id)->where('status', '!=', 'cancelled')->pluck('amount') as $value) {
                    $reserved = $reserved->plus($value);
                }
                $captured = app(TenantOrderService::class)->captured($order);
                if ($amount->isLessThanOrEqualTo('0') || $reserved->plus($amount)->isGreaterThan($captured) || $reserved->plus($amount)->isGreaterThan($order->grand_total)) {
                    throw ValidationException::withMessages(['refund' => 'The refund exceeds the original collected refundable balance or has no refundable amount.']);
                }
                $payments = $order->payments()->where('status', 'captured')->get();
                $payment = $payments->firstWhere('gateway', 'razorpay') ?? $payments->first();
                if (! $payment) {
                    throw ValidationException::withMessages(['refund' => 'No captured payment exists.']);
                }
                if ($payment->gateway === 'razorpay') {
                    $allocated = TenantOrderCalculationService::money('0');
                    foreach (Refund::where('payment_id', $payment->id)->where('status', '!=', 'cancelled')->pluck('amount') as $value) {
                        $allocated = $allocated->plus($value);
                    }
                    if (! $payment->gateway_payment_id || $allocated->plus($amount)->isGreaterThan($payment->amount)) {
                        throw ValidationException::withMessages(['refund' => 'The refund exceeds the original gateway payment balance.']);
                    }
                }
                $refund = Refund::create(['refund_number' => 'RFN-'.now()->format('Ym').'-'.Str::ulid(), 'return_request_id' => $return->id, 'order_id' => $order->id, 'payment_id' => $payment->id, 'amount' => (string) $amount, 'currency' => $order->currency, 'payment_method' => $payment->method, 'gateway' => $payment->gateway === 'razorpay' ? 'razorpay' : null, 'status' => 'pending', 'initiated_at' => now(), 'created_by' => $actor->id]);
                $this->audit->recordSnapshot($refund, 'initiated', null, ['amount' => $refund->amount, 'actor_id' => $actor->id], $actor);
                $this->setReturnStatus($return, 'refund_pending');
            }
            if ($refund->gateway === 'razorpay') {
                $refund->attempts()->create(['idempotency_key' => (string) Str::uuid(), 'status' => 'processing']);
                $refund->update(['status' => 'processing', 'gateway_refund_id' => null, 'failure_code' => null, 'failure_reason' => null, 'failed_at' => null]);
                $this->setReturnStatus($return, 'refund_processing');
                $dispatch = true;
                $this->audit->recordSnapshot($refund, $retry ? 'retried' : 'processing', null, ['status' => 'processing', 'actor_id' => $actor->id], $actor);
            }

            return $refund;
        }, 3);
        if ($dispatch) {
            $attempt = $refund->attempts()->latest('id')->firstOrFail();
            try {
                $entity = $this->gateway->createRefund($refund->payment->gateway_payment_id, $this->gatewayPayload($refund, $attempt), $attempt->idempotency_key);
                $this->applyGateway($attempt->id, $entity);
            } catch (OrderPaymentGatewayException) {
                $this->audit->recordSnapshot($refund, 'reconciliation_required', null, ['status' => 'processing', 'attempt_id' => $attempt->id]);
            }
        }

        return $refund->refresh()->load('attempts');
    }

    /** Fetch authoritative status outside the transaction; ambiguous submissions retain their reservation. */
    public function reconcile(int $returnId): Refund
    {
        $refund = Refund::with(['payment', 'attempts'])->where('return_request_id', $returnId)->firstOrFail();
        if ($refund->gateway !== 'razorpay') {
            throw ValidationException::withMessages(['refund' => 'This is a manual refund.']);
        }
        $attempt = $refund->attempts->sortByDesc('id')->first();
        $entity = $attempt->gateway_refund_id ? $this->gateway->fetchRefund($attempt->gateway_refund_id) : $this->gateway->findRefund($refund->payment->gateway_payment_id, $attempt->idempotency_key);
        if (! $entity && $refund->status === 'processing') {
            $entity = $this->gateway->createRefund($refund->payment->gateway_payment_id, $this->gatewayPayload($refund, $attempt), $attempt->idempotency_key);
        }
        if ($entity) {
            $this->applyGateway($attempt->id, $entity);
        }

        return $refund->refresh()->load('attempts');
    }

    /** Verified webhook entities must match the immutable payment, amount and attempt receipt. */
    public function webhook(array $entity): void
    {
        $attempt = RefundAttempt::where('gateway_refund_id', $entity['id'])->first();
        if (! $attempt && ! empty($entity['receipt'])) {
            $attempt = RefundAttempt::where('idempotency_key', $entity['receipt'])->first();
        }
        if (! $attempt) {
            throw new OrderPaymentGatewayException;
        }
        $this->applyGateway($attempt->id, $entity);
    }

    public function applyGateway(int $attemptId, array $entity): void
    {
        DB::connection('tenant')->transaction(function () use ($attemptId, $entity): void {
            $initial = RefundAttempt::with('refund')->findOrFail($attemptId);
            Order::lockForUpdate()->findOrFail($initial->refund->order_id);
            $return = ReturnRequest::lockForUpdate()->findOrFail($initial->refund->return_request_id);
            $refund = Refund::lockForUpdate()->findOrFail($initial->refund_id);
            $attempt = RefundAttempt::lockForUpdate()->findOrFail($attemptId);
            $expected = TenantOrderCalculationService::money($refund->amount)->multipliedBy(100)->toInt();
            if (
                ! is_string($entity['id'] ?? null) || ! str_starts_with($entity['id'], 'rfnd_')
                || ($entity['payment_id'] ?? null) !== $refund->payment->gateway_payment_id
                || ($entity['amount'] ?? null) !== $expected || ($entity['currency'] ?? null) !== $refund->currency
                || ($entity['receipt'] ?? null) !== $attempt->idempotency_key
                || ($attempt->gateway_refund_id && $attempt->gateway_refund_id !== $entity['id'])
            ) {
                throw ValidationException::withMessages(['refund' => 'The gateway refund does not match the original refund reservation.']);
            }
            $status = match ($entity['status'] ?? null) {
                'processed' => 'processed',
                'failed' => 'failed',
                'pending' => 'processing',
                default => throw new OrderPaymentGatewayException
            };
            if (in_array($attempt->status, ['processed', 'failed'], true)) {
                return;
            }
            if ($attempt->status === $status && $attempt->gateway_refund_id === $entity['id']) {
                return;
            }
            $attempt->update(['gateway_refund_id' => $entity['id'], 'status' => $status, 'failure_code' => $status === 'failed' ? 'GATEWAY_REFUND_FAILED' : null, 'failure_reason' => $status === 'failed' ? 'The gateway reported a failed refund.' : null]);
            if ($refund->attempts()->latest('id')->value('id') !== $attempt->id) {
                return;
            }
            if ($refund->status === 'processed') {
                return;
            }
            $fields = ['gateway_refund_id' => $entity['id'], 'status' => $status, 'failure_code' => $attempt->failure_code, 'failure_reason' => $attempt->failure_reason];
            if ($status === 'processed') {
                $fields['processed_at'] = now();
            }
            if ($status === 'failed') {
                $fields['failed_at'] = now();
            }
            $refund->update($fields);
            $this->setReturnStatus($return, match ($status) {
                'processed' => 'refunded',
                'failed' => 'refund_failed',
                default => 'refund_processing'
            });
            $this->audit->recordSnapshot($refund, $status, null, ['status' => $status, 'gateway_refund_id' => $entity['id'], 'attempt_id' => $attempt->id]);
        }, 3);
    }

    /** @param array<string, mixed> $input Validated evidence of an externally completed manual refund. */
    public function manual(int $returnId, User $actor, array $input): Refund
    {
        return DB::connection('tenant')->transaction(function () use ($returnId, $actor, $input): Refund {
            $orderId = ReturnRequest::findOrFail($returnId)->order_id;
            Order::lockForUpdate()->findOrFail($orderId);
            $return = ReturnRequest::lockForUpdate()->findOrFail($returnId);
            $refund = Refund::where('return_request_id', $returnId)->lockForUpdate()->firstOrFail();
            if ($refund->gateway !== null) {
                throw ValidationException::withMessages(['refund' => 'A gateway refund cannot be confirmed manually.']);
            }
            if ($refund->status === 'processed') {
                return $refund;
            }
            if ($refund->status !== 'pending' || $return->status !== 'refund_pending') {
                throw ValidationException::withMessages(['refund' => 'The refund is not awaiting manual confirmation.']);
            }
            $refund->update(['status' => 'processed', 'manual_method' => $input['manual_method'], 'reference_number' => $input['reference_number'], 'processed_at' => $input['refund_date'], 'admin_note' => $input['admin_note']]);
            $this->setReturnStatus($return, 'refund_processing');
            $this->setReturnStatus($return, 'refunded');
            $this->audit->recordSnapshot($refund, 'manual_processed', null, ['status' => 'processed', 'actor_id' => $actor->id, 'method' => $input['manual_method'], 'reference' => $input['reference_number']], $actor);

            return $refund;
        }, 3);
    }

    private function setReturnStatus(ReturnRequest $return, string $status): void
    {
        if ($return->status === $status) {
            return;
        }
        if (! in_array($status, ReturnRequest::TRANSITIONS[$return->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid refund lifecycle transition.']);
        }
        $from = $return->status;
        $return->update(['status' => $status]);
        $this->audit->recordSnapshot($return, $status, ['status' => $from], ['status' => $status]);
    }

    /** @return array{amount: int, speed: string, receipt: string, notes: array{refund_number: string}} Immutable gateway request for submission or an idempotent replay. */
    private function gatewayPayload(Refund $refund, RefundAttempt $attempt): array
    {
        return ['amount' => TenantOrderCalculationService::money($refund->amount)->multipliedBy(100)->toInt(), 'speed' => 'normal', 'receipt' => $attempt->idempotency_key, 'notes' => ['refund_number' => $refund->refund_number]];
    }
}
