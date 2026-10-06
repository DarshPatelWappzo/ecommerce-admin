<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantReplacementRequest;
use App\Models\Tenant\Order;
use App\Models\Tenant\ReplacementRequest;
use App\Models\Tenant\ReturnReason;
use App\Repositories\TenantReplacementRepository;
use App\Services\TenantRefundService;
use App\Services\TenantReplacementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantReplacementController extends Controller
{
    /** List replacements using shared tenant filters.
     * @param  TenantReplacementRequest  $request  Authorized filters.
     * @param  TenantReplacementRepository  $records  Tenant persistence.
     * @return View|JsonResponse Paginated replacements.
     */
    public function index(TenantReplacementRequest $request, TenantReplacementRepository $records): View|JsonResponse
    {
        $data = $records->paginate($request->validated());
        if ($request->is('api/*')) {
            return response()->json($data);
        }
        $data = ['replacements' => $data, 'tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain'), 'canCreate' => $request->allowed('replacements.create'), 'reasons' => ReturnReason::where('status', true)->orderBy('sort_order')->get()];
        $data['createOrder'] = $data['canCreate'] && $request->filled('create_order_number') ? $records->deliveredOrder($request->validated('create_order_number')) : null;

        return view('tenant.replacements.index', $data);
    }

    /** Show the lifecycle and only permitted actions.
     * @param  TenantReplacementRequest  $request  Authorized viewer.
     * @param  int  $replacement  Replacement ID.
     * @param  TenantReplacementRepository  $records  Detail lookups.
     * @return View|JsonResponse Replacement details.
     */
    public function show(TenantReplacementRequest $request, int $replacement, TenantReplacementRepository $records): View|JsonResponse
    {
        $record = $records->details($replacement);
        $actions = array_values(array_filter(ReplacementRequest::TRANSITIONS[$record->status], fn(string $target): bool => $target !== 'converted_to_refund' && $request->allowed(ReplacementRequest::permission($target))));
        $data = ['replacement' => $record, 'actions' => $actions, 'canRefund' => in_array($record->status, ['out_of_stock', 'converted_to_refund'], true) && $request->allowed('refunds.initiate') && $request->allowed('replacements.update_status')];
        if ($request->is('api/*')) {
            $data = ['data' => $data];

            return response()->json($data);
        }
        $data = [...$data, 'tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain')];

        return view('tenant.replacements.show', $data);
    }

    /** Create a request on behalf of the original customer.
     * @param  TenantReplacementRequest  $request  Authorized creation payload.
     * @param  TenantReplacementService  $service  Shared business workflow.
     * @return JsonResponse|RedirectResponse New replacement.
     */
    public function store(TenantReplacementRequest $request, TenantReplacementService $service): JsonResponse|RedirectResponse
    {
        $order = Order::findOrFail($request->validated('order_id'));
        abort_unless($order->customer, 422, 'A replacement requires a registered customer.');
        $record = $service->create($order->id, $request->validated('order_item_id'), $order->customer, $request->validated(), $request->actor());

        return $this->respond($request, $record, 201);
    }

    /** Apply one authorized lifecycle action.
     * @param  TenantReplacementRequest  $request  Validated action.
     * @param  int  $replacement  Replacement ID.
     * @param  TenantReplacementService  $service  Locked workflow.
     * @return JsonResponse|RedirectResponse Updated replacement.
     */
    public function transition(TenantReplacementRequest $request, int $replacement, TenantReplacementService $service): JsonResponse|RedirectResponse
    {
        $record = $service->transition($replacement, $request->validated('status'), $request->actor(), $request->validated());

        return $this->respond($request, $record);
    }

    /** Hand off an inspected claim and initiate the existing refund service after commit.
     * @param  TenantReplacementRequest  $request  Authorized refund initiator.
     * @param  int  $replacement  Replacement ID.
     * @param  TenantReplacementService  $service  Claim conversion.
     * @param  TenantRefundService  $refunds  Existing payment workflow.
     * @return JsonResponse|RedirectResponse Refund-linked replacement.
     */
    public function refund(TenantReplacementRequest $request, int $replacement, TenantReplacementService $service, TenantRefundService $refunds): JsonResponse|RedirectResponse
    {
        $return = $service->convertToRefund($replacement, $request->actor());
        $refunds->initiate($return->id, $request->actor());

        return $this->respond($request, ReplacementRequest::findOrFail($replacement));
    }

    /** Format browser and staff API responses consistently.
     * @param  TenantReplacementRequest  $request  Authorized staff request.
     * @param  ReplacementRequest  $record  Saved replacement.
     * @param  int  $status  HTTP success code.
     * @return JsonResponse|RedirectResponse Saved result.
     */
    private function respond(TenantReplacementRequest $request, ReplacementRequest $record, int $status = 200): JsonResponse|RedirectResponse
    {
        $data = ['data' => $record->load(['shipment', 'histories', 'returnRequest.refund']), 'message' => 'Replacement updated.'];
        if ($request->is('api/*')) {
            return response()->json($data, $status);
        }

        return redirect()->route('tenant.replacements.show', $record)->with('success', $data['message']);
    }
}
