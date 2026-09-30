<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantPaymentRequest;
use App\Http\Resources\TenantOrderResource;
use App\Repositories\TenantOrderRepository;
use App\Repositories\TenantPaymentRepository;
use App\Services\TenantPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantPaymentController extends Controller
{
    /**
     * Initialize tenant payment readers and transactional operations.
     *
     * @param  TenantPaymentRepository  $payments  Payment queries.
     * @param  TenantOrderRepository  $orders  Order queries.
     * @param  TenantPaymentService  $service  Payment operations.
     */
    public function __construct(private readonly TenantPaymentRepository $payments, private readonly TenantOrderRepository $orders, private readonly TenantPaymentService $service) {}

    /**
     * List tenant payments with customer, order, method, status and date filters.
     *
     * @param  TenantPaymentRequest  $request  Authorized filters.
     * @return View|JsonResponse Paginated payments.
     */
    public function index(TenantPaymentRequest $request): View|JsonResponse
    {
        $payments = $this->payments->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $payments;

            return response()->json($data);
        }
        $data = $this->context($request);
        $data['payments'] = $payments;

        return view('tenant.payments.index', $data);
    }

    /**
     * Display one tenant payment and its processing timeline.
     *
     * @param  TenantPaymentRequest  $request  Authorized request.
     * @param  int  $payment  Tenant payment ID.
     * @return View|JsonResponse Payment details.
     */
    public function show(TenantPaymentRequest $request, int $payment): View|JsonResponse
    {
        $record = $this->payments->find($payment);
        if ($request->is('api/*')) {
            $data = [
                'data' => $record->withoutRelations(),
                'order_number' => $record->order->order_number,
                'events' => $record->order->paymentCheckout?->events ?? [],
            ];

            return response()->json($data);
        }
        $data = $this->context($request);
        $data['payment'] = $record;
        $data['order'] = $record->order;

        return view('tenant.payments.show', $data);
    }

    /**
     * Return enabled methods without exposing provider credentials.
     *
     * @param  TenantPaymentRequest  $request  Authorized request.
     * @return JsonResponse Available method identifiers and labels.
     */
    public function methods(TenantPaymentRequest $request): JsonResponse
    {
        $data = ['data' => TenantPaymentService::methods()];

        return response()->json($data);
    }

    /**
     * Start or resume the single provider checkout for an order.
     *
     * @param  TenantPaymentRequest  $request  Authorized staff request.
     * @param  int  $order  Tenant order ID.
     * @return JsonResponse Minimal checkout options.
     */
    public function initiate(TenantPaymentRequest $request, int $order): JsonResponse
    {
        $data = ['data' => $this->service->initiate($order, $request->actor(), $this->tenantDatabaseId($request))];

        return response()->json($data);
    }

    /**
     * Verify provider evidence and return the resulting order payment state.
     *
     * @param  TenantPaymentRequest  $request  Signed checkout result.
     * @param  int  $order  Tenant order ID.
     * @return JsonResponse Verified order summary and validated checkout signature.
     */
    public function verify(TenantPaymentRequest $request, int $order): JsonResponse
    {
        $record = $this->service->verify($order, $request->validated(), $this->tenantDatabaseId($request));
        $data = ['message' => 'Payment verification completed.', 'data' => (new TenantOrderResource($record))->resolve($request)];
        $data['data']['razorpay_signature'] = $request->validated('razorpay_signature');

        return response()->json($data);
    }

    /**
     * Show payment attempts and received/outstanding amounts for an order.
     *
     * @param  TenantPaymentRequest  $request  Authorized request.
     * @param  int  $order  Tenant order ID.
     * @return JsonResponse Current order payment state.
     */
    public function status(TenantPaymentRequest $request, int $order): JsonResponse
    {
        $record = $this->orders->details($order);
        $summary = (new TenantOrderResource($record))->resolve($request);
        $data = ['data' => [
            'order_id' => $record->id,
            'payment_status' => $record->payment_status,
            'currency' => $record->currency,
            'grand_total' => $record->grand_total,
            'received_amount' => $summary['received_amount'],
            'outstanding_amount' => $summary['outstanding_amount'],
            'payments' => $record->payments,
        ]];

        return response()->json($data);
    }

    /**
     * Record the outstanding COD balance once using the existing receipt ledger.
     *
     * @param  TenantPaymentRequest  $request  Authorized collection reference.
     * @param  int  $order  Tenant order ID.
     * @return JsonResponse|RedirectResponse Collection confirmation.
     */
    public function collect(TenantPaymentRequest $request, int $order): JsonResponse|RedirectResponse
    {
        $record = $this->service->collectCod($order, $request->validated('reference_number'), $request->actor());
        if ($request->is('api/*') || $request->expectsJson()) {
            $data = ['message' => 'COD payment collected.', 'data' => (new TenantOrderResource($record))->resolve($request)];

            return response()->json($data);
        }

        return redirect()->route('tenant.orders.show', $order)->with('success', 'COD payment collected.');
    }

    /**
     * Provide the shared tenant admin layout context.
     *
     * @param  TenantPaymentRequest  $request  Current staff request.
     * @return array<string, mixed> Layout values.
     */
    private function context(TenantPaymentRequest $request): array
    {
        return ['tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain')];
    }

    /**
     * Resolve the database from authenticated server context, never checkout input.
     *
     * @param  TenantPaymentRequest  $request  Authorized payment request.
     * @return int Resolved tenant database identifier.
     */
    private function tenantDatabaseId(TenantPaymentRequest $request): int
    {
        $id = $request->is('api/*')
            ? $request->actor()->currentAccessToken()?->tenant_database_id
            : $request->attributes->get('tenant_database')?->id;
        abort_unless($id, 403);

        return (int) $id;
    }
}
