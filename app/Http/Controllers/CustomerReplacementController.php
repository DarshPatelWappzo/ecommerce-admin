<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerReplacementRequest;
use App\Models\Tenant\Order;
use App\Models\Tenant\ReplacementRequest;
use App\Models\Tenant\ReturnReason;
use App\Repositories\TenantReplacementRepository;
use App\Services\TenantReplacementEligibilityService;
use App\Services\TenantReplacementService;
use Illuminate\Http\JsonResponse;

class CustomerReplacementController extends Controller
{
    /** Cancel an owned request before pickup.
     * @param  CustomerReplacementRequest  $request  Authenticated customer.
     * @param  int  $replacement  Replacement ID.
     * @param  TenantReplacementService  $service  Locked workflow.
     * @return JsonResponse Updated customer-safe record.
     */
    public function cancel(CustomerReplacementRequest $request, int $replacement, TenantReplacementService $service): JsonResponse
    {
        $record = $service->transition($replacement, 'cancelled', $request->user());
        $data = ['data' => $this->customerData($record), 'message' => 'Replacement cancelled.'];

        return response()->json($data);
    }

    /** Return current policy and quantity for an owned order item.
     * @param  CustomerReplacementRequest  $request  Authenticated customer.
     * @param  int  $order  Parent order ID.
     * @param  int  $item  Child item ID.
     * @param  TenantReplacementEligibilityService  $eligibility  Shared eligibility calculation.
     * @return JsonResponse Policy envelope.
     */
    public function eligibility(CustomerReplacementRequest $request, int $order, int $item, TenantReplacementEligibilityService $eligibility): JsonResponse
    {
        $record = Order::where('customer_id', $request->user()->id)->findOrFail($order)->items()->findOrFail($item);
        $data = ['data' => ['replacement' => $eligibility->check($record)]];

        return response()->json($data);
    }

    /** Request a quantity-level replacement for an owned order item.
     * @param  CustomerReplacementRequest  $request  Validated customer payload.
     * @param  int  $order  Parent order ID.
     * @param  int  $item  Child item ID.
     * @param  TenantReplacementService  $replacements  Locked replacement workflow.
     * @return JsonResponse Created customer replacement.
     */
    public function store(CustomerReplacementRequest $request, int $order, int $item, TenantReplacementService $replacements): JsonResponse
    {
        $record = $replacements->create($order, $item, $request->user(), $request->validated());
        $data = ['data' => $this->customerData($record), 'message' => 'Replacement requested.'];

        return response()->json($data, 201);
    }

    /** List only the signed-in customer's replacements.
     * @param  CustomerReplacementRequest  $request  Authenticated customer.
     * @param  TenantReplacementRepository  $replacements  Tenant replacement lookups.
     * @return JsonResponse Paginated customer records.
     */
    public function index(CustomerReplacementRequest $request, TenantReplacementRepository $replacements): JsonResponse
    {
        $data = $replacements->paginate($request->validated(), $request->user()->id)->through(fn($record) => $this->customerData($record));

        return response()->json($data);
    }

    /** Show an owned replacement without staff notes.
     * @param  CustomerReplacementRequest  $request  Authenticated customer.
     * @param  int  $replacement  Replacement ID.
     * @return JsonResponse Customer return envelope.
     */
    public function show(CustomerReplacementRequest $request, int $replacement): JsonResponse
    {
        $record = ReplacementRequest::with(['reason', 'shipment', 'histories', 'returnRequest.refund'])->where('customer_id', $request->user()->id)->findOrFail($replacement);
        $data = ['data' => $this->customerData($record)];

        return response()->json($data);
    }

    /** List configurable active reasons.
     * @param  CustomerReplacementRequest  $request  Authenticated customer.
     * @return JsonResponse Ordered reason options.
     */
    public function reasons(CustomerReplacementRequest $request): JsonResponse
    {
        $data = ['data' => ReturnReason::where('status', true)->orderBy('sort_order')->get(['id', 'name'])];

        return response()->json($data);
    }

    /** Build the customer-safe replacement representation.
     * @param  ReplacementRequest  $record  Tenant replacement.
     * @return array<string, mixed> Customer-visible fields.
     */
    private function customerData(ReplacementRequest $record): array
    {
        $record->loadMissing(['reason', 'shipment', 'histories', 'returnRequest.refund']);

        return [...$record->only(['id', 'replacement_number', 'order_id', 'order_item_id', 'quantity', 'status', 'reason_note', 'requested_at', 'approved_at', 'received_at', 'qc_completed_at', 'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at']), 'reason' => $record->reason->name, 'shipment' => $record->shipment?->only(['courier_name', 'tracking_number', 'tracking_url', 'status', 'shipped_at', 'delivered_at']), 'timeline' => $record->histories->map(fn($history) => $history->only(['to_status', 'created_at'])), 'refund_status' => $record->returnRequest?->refund?->status];
    }
}
