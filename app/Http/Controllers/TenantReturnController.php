<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantReturnActionRequest;
use App\Http\Requests\TenantReturnRequest;
use App\Models\Tenant\ReturnReason;
use App\Models\Tenant\ReturnRequest;
use App\Repositories\TenantReturnRepository;
use App\Services\TenantAuditLogService;
use App\Services\TenantOrderCalculationService;
use App\Services\TenantRefundService;
use App\Services\TenantReturnEligibilityService;
use App\Services\TenantReturnService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class TenantReturnController extends Controller
{
    /** List tenant returns with filters.
     * @param  TenantReturnRequest  $request  Authorized staff filters.
     * @param  TenantReturnRepository  $returns  Tenant persistence.
     * @return View|JsonResponse Paginated returns.
     */
    public function index(TenantReturnRequest $request, TenantReturnRepository $returns): View|JsonResponse
    {
        $records = $returns->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $records;

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['returns'] = $records;
        $data['canManageReasons'] = $request->allowed('returns.reasons');

        return view('tenant.returns.index', $data);
    }

    /** Show policy, financial snapshots and authorized valid actions.
     * @param  TenantReturnRequest  $request  Authorized tenant viewer.
     * @param  int  $return  Return ID.
     * @param  TenantReturnRepository  $returns  Detail lookups.
     * @param  TenantReturnEligibilityService  $eligibility  Policy calculations.
     * @return View|JsonResponse Return details.
     */
    public function show(TenantReturnRequest $request, int $return, TenantReturnRepository $returns, TenantReturnEligibilityService $eligibility): View|JsonResponse
    {
        $record = $returns->details($return);
        $data = ['return' => $record, 'policy' => $eligibility->check($record->item), 'actions' => []];
        $paid = TenantOrderCalculationService::money('0');
        foreach ($record->order->payments->where('status', 'captured') as $payment) {
            $paid = $paid->plus($payment->amount);
        }
        $data['paidAmount'] = (string) $paid;
        foreach (ReturnRequest::TRANSITIONS[$record->status] as $target) {
            if ($target === 'closed' && $record->status === 'inspection_passed' && ! TenantOrderCalculationService::money($record->refund_amount)->isZero()) {
                continue;
            }
            $permission = match ($target) {
                'approved', 'rejected', 'cancelled' => 'returns.review',
                'received', 'in_transit' => 'returns.receive',
                'inspection_passed', 'inspection_failed' => 'returns.inspect',
                'closed' => 'returns.close',
                default => null,
            };
            if ($permission && $request->allowed($permission)) {
                $data['actions'][] = $target;
            }
        }
        foreach (['initiate', 'retry', 'reconcile', 'manual'] as $action) {
            $data['permissions'][$action] = $request->allowed('refunds.'.$action);
        }
        if ($request->is('api/*')) {
            $data = ['data' => $data];

            return response()->json($data);
        }

        $data = [...$this->viewData($request), ...$data];

        return view('tenant.returns.show', $data);
    }

    /** Apply an explicitly authorized return transition.
     * @param  TenantReturnActionRequest  $request  Validated decision.
     * @param  int  $return  Return ID.
     * @param  TenantReturnService  $service  Transactional workflow.
     * @return JsonResponse|RedirectResponse Saved action.
     */
    public function transition(TenantReturnActionRequest $request, int $return, TenantReturnService $service): JsonResponse|RedirectResponse
    {
        $record = $service->transition($return, $request->validated('status'), $request->actor(), $request->validated());

        return $this->respond($request, $record->id);
    }

    /** Reserve and initiate a refund after inspection.
     * @param  TenantReturnActionRequest  $request  Authorized refund initiator.
     * @param  int  $return  Return ID.
     * @param  TenantRefundService  $refunds  Financial workflow.
     * @return JsonResponse|RedirectResponse Refund result.
     */
    public function initiate(TenantReturnActionRequest $request, int $return, TenantRefundService $refunds): JsonResponse|RedirectResponse
    {
        $refunds->initiate($return, $request->actor());

        return $this->respond($request, $return);
    }

    /** Retry only a confirmed failed gateway refund.
     * @param  TenantReturnActionRequest  $request  Authorized retry.
     * @param  int  $return  Return ID.
     * @param  TenantRefundService  $refunds  Financial workflow.
     * @return JsonResponse|RedirectResponse Retry result.
     */
    public function retry(TenantReturnActionRequest $request, int $return, TenantRefundService $refunds): JsonResponse|RedirectResponse
    {
        $refunds->initiate($return, $request->actor(), true);

        return $this->respond($request, $return);
    }

    /** Refresh authoritative gateway refund status.
     * @param  TenantReturnActionRequest  $request  Authorized reconciliation.
     * @param  int  $return  Return ID.
     * @param  TenantRefundService  $refunds  Financial workflow.
     * @return JsonResponse|RedirectResponse Current refund state.
     */
    public function reconcile(TenantReturnActionRequest $request, int $return, TenantRefundService $refunds): JsonResponse|RedirectResponse
    {
        $refunds->reconcile($return);

        return $this->respond($request, $return);
    }

    /** Record evidence of a manual payout.
     * @param  TenantReturnActionRequest  $request  Validated payout reference.
     * @param  int  $return  Return ID.
     * @param  TenantRefundService  $refunds  Financial workflow.
     * @return JsonResponse|RedirectResponse Confirmed manual refund.
     */
    public function manual(TenantReturnActionRequest $request, int $return, TenantRefundService $refunds): JsonResponse|RedirectResponse
    {
        $refunds->manual($return, $request->actor(), $request->validated());

        return $this->respond($request, $return);
    }

    /** Manage configurable return reasons.
     * @param  TenantReturnRequest  $request  Authorized reason manager.
     * @return View|JsonResponse Current reasons.
     */
    public function reasons(TenantReturnRequest $request): View|JsonResponse
    {
        $data = ['reasons' => ReturnReason::orderBy('sort_order')->get()];
        if ($request->is('api/*')) {
            $data = ['data' => $data['reasons']];

            return response()->json($data);
        }

        $data = [...$this->viewData($request), ...$data];

        return view('tenant.returns.reasons', $data);
    }

    /** Save a reason without deleting historical references.
     * @param  TenantReturnActionRequest  $request  Validated reason fields.
     * @param  TenantAuditLogService  $audit  Existing tenant audit service.
     * @return JsonResponse|RedirectResponse Saved reason.
     */
    public function saveReason(TenantReturnActionRequest $request, TenantAuditLogService $audit): JsonResponse|RedirectResponse
    {
        $record = DB::connection('tenant')->transaction(function () use ($request, $audit): ReturnReason {
            $record = $request->filled('id') ? ReturnReason::lockForUpdate()->findOrFail($request->validated('id')) : new ReturnReason;
            $old = $record->exists ? $record->only(['name', 'status', 'sort_order']) : null;
            $record->fill($request->safe()->only(['name', 'status', 'sort_order']))->save();
            $audit->recordSnapshot($record, 'saved', $old, $record->only(['name', 'status', 'sort_order']));

            return $record;
        }, 3);
        $data = ['data' => $record, 'message' => 'Return reason saved.'];
        if ($request->is('api/*')) {
            return response()->json($data);
        }

        return redirect()->route('tenant.returns.reasons')->with('success', $data['message']);
    }

    /** Preserve staff API envelopes and browser notifications.
     * @param  TenantReturnRequest  $request  Authorized staff request.
     * @param  int  $id  Return ID.
     * @return JsonResponse|RedirectResponse Updated return.
     */
    private function respond(TenantReturnRequest $request, int $id): JsonResponse|RedirectResponse
    {
        $data = ['data' => app(TenantReturnRepository::class)->details($id), 'message' => 'Return updated.'];
        if ($request->is('api/*')) {
            return response()->json($data);
        }

        return redirect()->route('tenant.returns.show', $id)->with('success', $data['message']);
    }

    /** Build shared tenant layout context.
     * @param  TenantReturnRequest  $request  Authenticated staff request.
     * @return array<string, mixed> Header context.
     */
    private function viewData(TenantReturnRequest $request): array
    {
        return ['tenantUser' => $request->actor(), 'tenantDomain' => session('tenant_domain')];
    }
}
