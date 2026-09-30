<?php

namespace App\Http\Controllers;

use App\Models\Tenant\OrderPaymentCheckout;
use App\Models\TenantDatabase;
use App\Services\OrderPaymentGateway;
use App\Services\OrderPaymentGatewayException;
use App\Services\TenantConnectionManager;
use App\Services\TenantPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        abort_unless(config('order_payments.razorpay.webhook_secret'), 404);

        if (! $this->gateway->verifyWebhook($request->getContent(), (string) $request->header('X-Razorpay-Signature'))) {
            $data = ['message' => 'Invalid webhook signature.', 'error_code' => 401];

            return response()->json($data, 401);
        }
        $input = json_decode($request->getContent(), true);
        Validator::make(is_array($input) ? $input : [], [
            'event' => ['required', 'string'],
        ])->validate();
        if (! in_array($input['event'], ['payment.captured', 'payment.authorized', 'payment.failed', 'order.paid'], true)) {
            $data = ['message' => 'Event ignored.'];

            return response()->json($data);
        }
        Validator::make($input, [
            'payload.payment.entity.id' => ['required', 'string', 'max:100', 'regex:/^pay_[A-Za-z0-9]+$/'],
            'payload.payment.entity.order_id' => ['required', 'string', 'max:100', 'regex:/^order_[A-Za-z0-9]+$/'],
        ])->validate();
        $providerOrder = $this->gateway->fetchOrder(data_get($input, 'payload.payment.entity.order_id'));
        if (data_get($providerOrder, 'notes.purpose') !== 'tenant_order') {
            $data = ['message' => 'Event ignored.'];

            return response()->json($data);
        }
        $tenant = TenantDatabase::with('domain')->find(data_get($providerOrder, 'notes.tenant_database_id'));
        if (! $tenant || $tenant->status !== 'active' || ! $tenant->domain) {
            throw new OrderPaymentGatewayException;
        }
        try {
            $this->connections->connect($tenant);
            $checkout = OrderPaymentCheckout::where('reference', $providerOrder['receipt'] ?? '')->first();
            if (! $checkout) {
                throw new OrderPaymentGatewayException;
            }
            $entity = $this->gateway->fetchPayment(data_get($input, 'payload.payment.entity.id'));
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
        } finally {
            $this->connections->disconnect();
        }
        $data = ['message' => 'Webhook processed.'];

        return response()->json($data);
    }
}
