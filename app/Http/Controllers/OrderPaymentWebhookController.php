<?php

namespace App\Http\Controllers;

use App\Models\Tenant\OrderPayment;
use App\Models\Tenant\OrderPaymentCheckout;
use App\Models\TenantDatabase;
use App\Services\OrderPaymentGateway;
use App\Services\OrderPaymentGatewayException;
use App\Services\PaymentGatewayService;
use App\Services\RazorpayRefundGateway;
use App\Services\TenantConnectionManager;
use App\Services\TenantPaymentService;
use App\Services\TenantRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderPaymentWebhookController extends Controller
{
    /**
     * Initialize signature verification, tenant selection and reconciliation.
     *
     * @param  OrderPaymentGateway  $gateway  Customer order provider.
     * @param  TenantConnectionManager  $connections  Tenant connection lifecycle.
     * @param  TenantPaymentService  $payments  Payment reconciliation.
     */
    public function __construct(private readonly OrderPaymentGateway $gateway, private readonly TenantConnectionManager $connections, private readonly TenantPaymentService $payments) {}

    /**
     * Verify raw webhook bytes before using provider-owned routing metadata.
     *
     * @param  Request  $request  Provider webhook.
     * @return JsonResponse Idempotent acknowledgement or a retryable failure.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $request->route('tenantId');
        $credentialId = (int) $request->route('credentialId');
        abort_unless($tenantId && $credentialId, 404);
        $tenant = TenantDatabase::with('domain')->where('status', 'active')->find($tenantId);
        abort_unless($tenant?->domain, 404);
        try {
            $this->connections->connect($tenant);

            return $this->process($request, $tenant, $credentialId);
        } finally {
            $this->connections->disconnect();
        }
    }

    /**
     * Verify the selected tenant/version before using any event data.
     *
     * @param  Request  $request  Raw provider event.
     * @param  TenantDatabase  $tenant  Server-resolved tenant.
     * @param  int  $credentialId  Immutable credential version.
     * @return JsonResponse Committed acknowledgement.
     */
    private function process(Request $request, TenantDatabase $tenant, int $credentialId): JsonResponse
    {
        try {
            $valid = $this->gateway->verifyWebhook($request->getContent(), (string) $request->header('X-Razorpay-Signature'), $credentialId);
        } catch (OrderPaymentGatewayException) {
            $valid = false;
        }
        if (! $valid) {
            Log::notice('Tenant payment webhook signature rejected.', ['tenant_database_id' => $tenant->id, 'version_id' => $credentialId]);
            $data = ['message' => 'Invalid webhook signature.', 'error_code' => 401];

            return response()->json($data, 401);
        }
        $input = json_decode($request->getContent(), true);
        Validator::make(is_array($input) ? $input : [], [
            'event' => ['required', 'string'],
        ])->validate();
        if (in_array($input['event'], ['refund.created', 'refund.processed', 'refund.failed'], true)) {
            return $this->refund($input, $tenant, $credentialId);
        }
        if (! in_array($input['event'], ['payment.captured', 'payment.authorized', 'payment.failed', 'order.paid'], true)) {
            $data = ['message' => 'Event ignored.'];

            return response()->json($data);
        }
        Validator::make($input, [
            'payload.payment.entity.id' => ['required', 'string', 'max:100', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'payload.payment.entity.order_id' => ['required', 'string', 'max:100', 'regex:/^order_[A-Za-z0-9]+$/'],
        ])->validate();
        $providerOrder = $this->gateway->fetchOrder(data_get($input, 'payload.payment.entity.order_id'), $credentialId);
        if (data_get($providerOrder, 'notes.purpose') !== 'tenant_order') {
            $data = ['message' => 'Event ignored.'];

            return response()->json($data);
        }
        if ((string) data_get($providerOrder, 'notes.tenant_database_id') !== (string) $tenant->id) {
            throw new OrderPaymentGatewayException;
        }
        try {
            $checkout = OrderPaymentCheckout::where('reference', $providerOrder['receipt'] ?? '')->first();
            if (! $checkout || app(PaymentGatewayService::class)->versionFor($checkout) !== $credentialId) {
                throw new OrderPaymentGatewayException;
            }
            $entity = $this->gateway->fetchPayment(data_get($input, 'payload.payment.entity.id'), $credentialId);
            if (($entity['id'] ?? null) !== data_get($input, 'payload.payment.entity.id')) {
                throw ValidationException::withMessages(['payment' => 'The provider payment does not match.']);
            }
            $eventId = $request->header('X-Razorpay-Event-Id') ?: hash('sha256', $request->getContent());
            Validator::make(['event_id' => $eventId], ['event_id' => ['required', 'string', 'max:191']])->validate();
            $this->payments->reconcile($checkout, $providerOrder, $entity, $tenant->id, [
                'event_id' => $eventId,
                'type' => $input['event'],
                'payload_hash' => hash('sha256', $request->getContent()),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new OrderPaymentGatewayException;
        }
        $data = ['message' => 'Webhook processed.'];

        return response()->json($data);
    }

    /**
     * Route a verified refund event using provider-fetched payment and order metadata.
     *
     * @param  array<string, mixed>  $input  Signature-verified event payload.
     * @param  TenantDatabase  $tenant  URL-selected tenant.
     * @param  int  $credentialId  Signature-verified credential version.
     * @return JsonResponse Acknowledgement after the tenant financial transaction commits.
     */
    private function refund(array $input, TenantDatabase $tenant, int $credentialId): JsonResponse
    {
        Validator::make($input, ['payload.refund.entity.id' => ['required', 'string', 'max:100', 'regex:/^rfnd_[A-Za-z0-9]+$/']])->validate();
        $gateway = app(RazorpayRefundGateway::class);
        $entity = $gateway->fetchRefund(data_get($input, 'payload.refund.entity.id'), $credentialId);
        if (($entity['id'] ?? null) !== data_get($input, 'payload.refund.entity.id')) {
            throw new OrderPaymentGatewayException;
        }
        $payment = $this->gateway->fetchPayment($entity['payment_id'] ?? '', $credentialId);
        if (($payment['id'] ?? null) !== ($entity['payment_id'] ?? null)) {
            throw new OrderPaymentGatewayException;
        }
        $providerOrder = $this->gateway->fetchOrder($payment['order_id'] ?? '', $credentialId);
        if (($providerOrder['id'] ?? null) !== ($payment['order_id'] ?? null)) {
            throw new OrderPaymentGatewayException;
        }
        if (data_get($providerOrder, 'notes.purpose') !== 'tenant_order') {
            $data = ['message' => 'Event ignored.'];

            return response()->json($data);
        }
        if ((string) data_get($providerOrder, 'notes.tenant_database_id') !== (string) $tenant->id) {
            throw new OrderPaymentGatewayException;
        }
        $localPayment = OrderPayment::where('gateway', 'razorpay')->where('gateway_payment_id', $payment['id'])->where('gateway_order_id', $providerOrder['id'])->first();
        if (! $localPayment || app(PaymentGatewayService::class)->versionFor($localPayment) !== $credentialId) {
            throw new OrderPaymentGatewayException;
        }
        app(TenantRefundService::class)->webhook($entity);
        $data = ['message' => 'Refund webhook processed.'];

        return response()->json($data);
    }
}
