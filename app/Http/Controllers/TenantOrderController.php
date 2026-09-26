<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantOrderActionRequest;
use App\Http\Requests\TenantOrderRequest;
use App\Http\Requests\TenantOrderSaveRequest;
use App\Http\Resources\TenantOrderResource;
use App\Models\Tenant\Order;
use App\Repositories\TenantOrderRepository;
use App\Services\TenantOrderCalculationService;
use App\Services\TenantOrderIdempotencyService;
use App\Services\TenantOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantOrderController extends Controller
{
    /**
     * Initialize the order workflow dependencies.
     *
     * @param  TenantOrderRepository  $orders  Tenant order persistence and lookups.
     * @param  TenantOrderService  $service  Transactional order lifecycle operations.
     * @param  TenantOrderCalculationService  $calculator  Authoritative decimal calculations.
     * @param  TenantOrderIdempotencyService  $idempotency  Durable request replay protection.
     */
    public function __construct(private readonly TenantOrderRepository $orders, private readonly TenantOrderService $service, private readonly TenantOrderCalculationService $calculator, private readonly TenantOrderIdempotencyService $idempotency) {}

    /**
     * List orders using tenant filters and the existing paginator envelope.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @return View|JsonResponse The action response.
     */
    public function index(TenantOrderRequest $request): View|JsonResponse
    {
        $orders = $this->orders->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $orders->through(fn (Order $order) => (new TenantOrderResource($order))->resolve($request));

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['orders'] = $orders;

        return view('tenant.orders.index', $data);
    }

    /**
     * Show the order creation form.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @return View The action response.
     */
    public function create(TenantOrderRequest $request): View
    {
        $data = $this->viewData($request);
        $data['order'] = null;
        $data['taxes'] = $this->orders->taxes();

        return view('tenant.orders.form', $data);
    }

    /**
     * Show a draft for editing, without allowing confirmed snapshot changes.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return View The action response.
     */
    public function edit(TenantOrderRequest $request, int $order): View
    {
        $record = $this->orders->details($order);
        abort_unless($record->status === 'draft', 403, 'Only draft orders can be edited.');
        $data = $this->viewData($request);
        $data['order'] = $record;
        $data['taxes'] = $this->orders->taxes();

        return view('tenant.orders.form', $data);
    }

    /**
     * Return order snapshots and operational history.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return View|JsonResponse The action response.
     */
    public function show(TenantOrderRequest $request, int $order): View|JsonResponse
    {
        $record = $this->orders->details($order);
        if ($request->is('api/*')) {
            $data = ['data' => new TenantOrderResource($record)];

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['order'] = $record;
        $data['summary'] = (new TenantOrderResource($record))->resolve($request);

        return view('tenant.orders.show', $data);
    }

    /**
     * Return bounded tenant customer, variant and tax choices for Select2.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @return JsonResponse The action response.
     */
    public function options(TenantOrderRequest $request): JsonResponse
    {
        $options = $this->orders->options($request->validated());
        $kind = $request->input('kind');
        $data = ['results' => $options->getCollection()->map(function ($record) use ($kind): array {
            if ($kind === 'customers') {
                return [
                    'id' => $record->id,
                    'text' => trim($record->first_name.' '.$record->last_name).' - '.$record->customer_code,
                    'profile' => $record->only(['first_name', 'last_name', 'email', 'phone', 'phone_country_code', 'company_name', 'gstin']),
                    'addresses' => $record->addresses->toArray(),
                ];
            }
            if ($kind === 'taxes') {
                return ['id' => $record->id, 'text' => $record->name.' - '.$record->rate.'%', 'name' => $record->name, 'code' => $record->code, 'rate' => $record->rate];
            }
            $price = $this->calculator->catalogPrice($record);

            return [
                'id' => $record->id,
                'text' => $record->product->name.' - '.$record->sku.' - INR '.$price.' - Available '.($record->quantity - $record->reserved_quantity),
                'product_id' => $record->product_id,
                'price' => $price,
            ];
        })->all(), 'pagination' => ['more' => $options->hasMorePages()]];

        return response()->json($data);
    }

    /**
     * Calculate authoritative totals without persisting or reserving inventory.
     *
     * @param  TenantOrderSaveRequest  $request  The authorized tenant request.
     * @return JsonResponse The action response.
     */
    public function preview(TenantOrderSaveRequest $request): JsonResponse
    {
        $data = ['data' => $this->calculator->calculate($request->validated())];

        return response()->json($data);
    }

    /**
     * Create an order once and replay its original result for identical retries.
     *
     * @param  TenantOrderSaveRequest  $request  The authorized tenant request.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function store(TenantOrderSaveRequest $request): JsonResponse|RedirectResponse
    {
        $data = $this->idempotency->run($request->actor()->id, 'orders.create', $request->validated('idempotency_key'), $this->fingerprintInput($request), function () use ($request): array {
            $order = $this->service->save($request->validated(), $request->actor(), $request->is('api/*') ? 'api' : 'admin');

            return ['data' => (new TenantOrderResource($order))->resolve($request), 'message' => 'Order saved successfully.'];
        });

        return $this->respond($request, $data, 201);
    }

    /**
     * Reprice and update only a draft order.
     *
     * @param  TenantOrderSaveRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function update(TenantOrderSaveRequest $request, int $order): JsonResponse|RedirectResponse
    {
        $record = $this->service->save($request->validated(), $request->actor(), $request->is('api/*') ? 'api' : 'admin', $order);
        $data = ['data' => (new TenantOrderResource($record))->resolve($request), 'message' => 'Order updated successfully.'];

        return $this->respond($request, $data);
    }

    /**
     * Confirm and reserve stock after rechecking availability.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function confirm(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, 'confirmed');
    }

    /**
     * Begin processing while retaining the reservation.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function process(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, 'processing');
    }

    /**
     * Cancel an eligible unpaid order and release its reservation once.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function cancel(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, 'cancelled');
    }

    /**
     * Ship once, consuming on-hand stock and its reservation.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function ship(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, 'shipped');
    }

    /**
     * Mark a shipped order delivered without another stock deduction.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function deliver(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        return $this->transition($request, $order, 'delivered');
    }

    /**
     * Record a verified offline receipt with durable idempotency.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @return JsonResponse|RedirectResponse The action response.
     */
    public function payments(TenantOrderActionRequest $request, int $order): JsonResponse|RedirectResponse
    {
        $data = $this->idempotency->run($request->actor()->id, 'orders.payment.'.$order, $request->validated('idempotency_key'), $this->fingerprintInput($request), function () use ($request, $order): array {
            $record = $this->service->payment($order, $request->validated(), $request->actor());

            return ['data' => (new TenantOrderResource($record))->resolve($request), 'message' => 'Payment recorded successfully.'];
        });

        return $this->respond($request, $data, 201);
    }

    /**
     * Apply a status action through the shared transactional service.
     *
     * @param  TenantOrderActionRequest  $request  The authorized tenant request.
     * @param  int  $order  The tenant order identifier.
     * @param  string  $status  The status dependency or value.
     * @return JsonResponse|RedirectResponse The action response.
     */
    private function transition(TenantOrderActionRequest $request, int $order, string $status): JsonResponse|RedirectResponse
    {
        $record = $this->service->transition($order, $status, $request->actor(), $request->validated());
        $data = ['data' => (new TenantOrderResource($record))->resolve($request), 'message' => 'Order '.$status.'.'];

        return $this->respond($request, $data);
    }

    /**
     * Build shared layout data and capability flags.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @return array The prepared response data.
     */
    private function viewData(TenantOrderRequest $request): array
    {
        $data = ['tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain')];
        foreach (['view', 'create', 'update', 'confirm', 'process', 'cancel', 'payments', 'ship', 'deliver', 'price_override', 'discount'] as $permission) {
            $data['permissions'][$permission] = $request->allowed($permission);
        }

        return $data;
    }

    /**
     * Fingerprint normalized client fields, excluding transport and retry tokens.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @return array The prepared response data.
     */
    private function fingerprintInput(TenantOrderRequest $request): array
    {
        return ['source' => $request->is('api/*') ? 'api' : 'admin', 'input' => $request->except(['_token', '_method', 'idempotency_key'])];
    }

    /**
     * Preserve API envelopes and browser notifications.
     *
     * @param  TenantOrderRequest  $request  The authorized tenant request.
     * @param  array  $data  The data dependency or value.
     * @param  int  $status  The status dependency or value.
     * @return JsonResponse|RedirectResponse The action response.
     */
    private function respond(TenantOrderRequest $request, array $data, int $status = 200): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            return response()->json($data, $status);
        }
        $redirect = route('tenant.orders.show', $data['data']['id']);
        if ($request->expectsJson()) {
            $request->session()->flash('success', $data['message']);
            $data['redirect'] = $redirect;

            return response()->json($data, $status);
        }

        return redirect($redirect)->with('success', $data['message']);
    }
}
