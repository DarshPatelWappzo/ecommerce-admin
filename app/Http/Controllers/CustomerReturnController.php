<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerReturnRequest;
use App\Models\Tenant\Order;
use App\Models\Tenant\ReturnReason;
use App\Models\Tenant\ReturnRequest;
use App\Repositories\TenantReturnRepository;
use App\Services\TenantReturnEligibilityService;
use App\Services\TenantReturnService;
use Illuminate\Http\JsonResponse;

class CustomerReturnController extends Controller
{
    /** Return current policy and quantity for an owned order item.
     * @param  CustomerReturnRequest  $request  Authenticated customer.
     * @param  int  $order  Parent order ID.
     * @param  int  $item  Child item ID.
     * @param  TenantReturnEligibilityService  $eligibility  Shared eligibility calculation.
     * @return JsonResponse Policy envelope.
     */
    public function eligibility(CustomerReturnRequest $request, int $order, int $item, TenantReturnEligibilityService $eligibility): JsonResponse
    {
        $record = Order::where('customer_id', $request->user()->id)->findOrFail($order)->items()->findOrFail($item);
        $data = ['data' => ['return' => $eligibility->check($record)]];

        return response()->json($data);
    }

    /** Request a quantity-level return for an owned order item.
     * @param  CustomerReturnRequest  $request  Validated customer payload.
     * @param  int  $order  Parent order ID.
     * @param  int  $item  Child item ID.
     * @param  TenantReturnService  $returns  Locked return workflow.
     * @return JsonResponse Created customer return.
     */
    public function store(CustomerReturnRequest $request, int $order, int $item, TenantReturnService $returns): JsonResponse
    {
        $record = $returns->create($order, $item, $request->user(), $request->validated());
        $data = ['data' => $this->customerData($record), 'message' => 'Return requested.'];

        return response()->json($data, 201);
    }

    /** List only the signed-in customer's returns.
     * @param  CustomerReturnRequest  $request  Authenticated customer.
     * @param  TenantReturnRepository  $returns  Tenant return lookups.
     * @return JsonResponse Paginated customer records.
     */
    public function index(CustomerReturnRequest $request, TenantReturnRepository $returns): JsonResponse
    {
        $data = $returns->paginate($request->validated(), $request->user()->id)->through(fn($record) => $this->customerData($record));

        return response()->json($data);
    }

    /** Show an owned return without staff notes.
     * @param  CustomerReturnRequest  $request  Authenticated customer.
     * @param  int  $return  Return ID.
     * @return JsonResponse Customer return envelope.
     */
    public function show(CustomerReturnRequest $request, int $return): JsonResponse
    {
        $record = ReturnRequest::with(['reason', 'refund'])->where('customer_id', $request->user()->id)->findOrFail($return);
        $data = ['data' => $this->customerData($record)];

        return response()->json($data);
    }

    /** List configurable active reasons.
     * @param  CustomerReturnRequest  $request  Authenticated customer.
     * @return JsonResponse Ordered reason options.
     */
    public function reasons(CustomerReturnRequest $request): JsonResponse
    {
        $data = ['data' => ReturnReason::where('status', true)->orderBy('sort_order')->get(['id', 'name'])];

        return response()->json($data);
    }

    /** Build the customer-safe return representation.
     * @param  ReturnRequest  $record  Tenant return.
     * @return array<string, mixed> Customer-visible fields.
     */
    private function customerData(ReturnRequest $record): array
    {
        $record->loadMissing(['reason', 'refund']);

        return [...$record->only(['id', 'return_number', 'order_id', 'order_item_id', 'quantity', 'status', 'reason_note', 'requested_at', 'refund_amount', 'rejection_reason']), 'reason' => $record->reason->name, 'refund_status' => $record->refund?->status];
    }
}
