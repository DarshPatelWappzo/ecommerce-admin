<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerOrderRequest;
use App\Repositories\CustomerShoppingRepository;
use App\Services\CustomerOrderService;
use App\Services\TenantInvoicePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CustomerOrderController extends Controller
{
    public function __construct(private readonly CustomerOrderService $orders, private readonly CustomerShoppingRepository $shopping) {}

    /** List the customer's historical order snapshots.
     * @param  CustomerOrderRequest  $request  Validated filters.
     * @return JsonResponse Paginated history.
     */
    public function index(CustomerOrderRequest $request): JsonResponse
    {
        $data = $this->orders->list($request->user(), $request->validated());

        return response()->json($data);
    }

    /** Show an owned order without internal operational fields.
     * @param  CustomerOrderRequest  $request  Authenticated customer.
     * @param  int  $order  Owned order identifier.
     * @return JsonResponse Order details.
     */
    public function show(CustomerOrderRequest $request, int $order): JsonResponse
    {
        $data = ['data' => $this->orders->detail($request->user(), $order)];

        return response()->json($data);
    }

    /** Return the original order shipment and tracking.
     * @param  CustomerOrderRequest  $request  Authenticated customer.
     * @param  int  $order  Owned order identifier.
     * @return JsonResponse Shipment envelope.
     */
    public function tracking(CustomerOrderRequest $request, int $order): JsonResponse
    {
        $record = $this->shopping->order($request->user(), $order);
        $data = ['data' => $record->shipment?->only(['status', 'courier_name', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at'])];

        return response()->json($data);
    }

    /** Download only the issued invoice of an owned order.
     * @param  CustomerOrderRequest  $request  Authenticated customer.
     * @param  int  $order  Owned order identifier.
     * @param  TenantInvoicePdfService  $pdf  Existing PDF renderer.
     * @return Response Invoice attachment.
     */
    public function invoice(CustomerOrderRequest $request, int $order, TenantInvoicePdfService $pdf): Response
    {
        $record = $this->shopping->order($request->user(), $order);
        abort_unless($record->invoice?->status === 'issued', 404, 'An issued invoice is not available.');
        $record->invoice->load(['items', 'order.payments']);

        return $pdf->download($record->invoice, $this->orders->invoicePayment($record));
    }

    /** Reorder all purchased lines using current catalog validation.
     * @param  CustomerOrderRequest  $request  Authenticated customer.
     * @param  int  $order  Owned order identifier.
     * @return JsonResponse Current cart.
     */
    public function reorder(CustomerOrderRequest $request, int $order): JsonResponse
    {
        $data = ['data' => $this->orders->reorder($request->user(), $order)];

        return response()->json($data);
    }

    /** Initiate, verify or reconcile an owned Razorpay order.
     * @param  CustomerOrderRequest  $request  Validated payment action.
     * @param  int  $order  Owned order identifier.
     * @return JsonResponse Safe order and checkout data.
     */
    public function payment(CustomerOrderRequest $request, int $order): JsonResponse
    {
        $data = ['data' => $this->orders->payment($request->user(), $order, $request->validated())];

        return response()->json($data);
    }
}
