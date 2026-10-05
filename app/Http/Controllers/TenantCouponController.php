<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantCouponRequest;
use App\Http\Requests\TenantCouponSaveRequest;
use App\Models\Tenant\Coupon;
use App\Repositories\TenantCouponRepository;
use App\Repositories\TenantRoleRepository;
use App\Services\TenantCouponService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantCouponController extends Controller
{
    /**
     * Initialize coupon persistence and tenant permission dependencies.
     *
     * @param  TenantCouponRepository  $coupons  Coupon queries.
     * @param  TenantCouponService  $service  Transactional writes.
     * @param  TenantRoleRepository  $roles  Tenant permissions.
     */
    public function __construct(private readonly TenantCouponRepository $coupons, private readonly TenantCouponService $service, private readonly TenantRoleRepository $roles) {}

    /**
     * List coupons and their consumed usage.
     *
     * @param  TenantCouponRequest  $request  Authorized filters.
     * @return View|JsonResponse Paginated coupons.
     */
    public function index(TenantCouponRequest $request): View|JsonResponse
    {
        $coupons = $this->coupons->paginate($request->validated());
        if ($request->is('api/*')) {
            $data = $coupons;

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['coupons'] = $coupons;

        return view('tenant.coupons.index', $data);
    }

    /**
     * Show a new coupon form.
     *
     * @param  TenantCouponRequest  $request  Authorized request.
     * @return View Creation form.
     */
    public function create(TenantCouponRequest $request): View
    {
        $data = $this->viewData($request);
        $data['coupon'] = new Coupon;
        $data['choices'] = $this->coupons->choices();

        return view('tenant.coupons.form', $data);
    }

    /**
     * Show the current tenant coupon for editing.
     *
     * @param  TenantCouponRequest  $request  Authorized request.
     * @param  Coupon  $coupon  Tenant bound coupon.
     * @return View Edit form.
     */
    public function edit(TenantCouponRequest $request, Coupon $coupon): View
    {
        $data = $this->viewData($request);
        $data['coupon'] = $coupon->load(['products', 'categories', 'customers']);
        $data['choices'] = $this->coupons->choices();

        return view('tenant.coupons.form', $data);
    }

    /**
     * Show coupon rules and usage.
     *
     * @param  TenantCouponRequest  $request  Authorized request.
     * @param  Coupon  $coupon  Tenant bound coupon.
     * @return View|JsonResponse Coupon details.
     */
    public function show(TenantCouponRequest $request, Coupon $coupon): View|JsonResponse
    {
        $coupon->load(['products', 'categories', 'customers'])->loadCount(['redemptions as usage_count' => fn ($query) => $query->whereNull('released_at')]);
        if ($request->is('api/*')) {
            $data = ['data' => $coupon];

            return response()->json($data);
        }
        $data = $this->viewData($request);
        $data['coupon'] = $coupon;

        return view('tenant.coupons.show', $data);
    }

    /**
     * Create a validated coupon.
     *
     * @param  TenantCouponSaveRequest  $request  Validated values.
     * @return RedirectResponse|JsonResponse Created coupon.
     */
    public function store(TenantCouponSaveRequest $request): RedirectResponse|JsonResponse
    {
        return $this->saved($request, $this->service->save($request->validated()), 'Coupon created.', 201);
    }

    /**
     * Replace coupon rules without changing submitted orders.
     *
     * @param  TenantCouponSaveRequest  $request  Validated values.
     * @param  Coupon  $coupon  Tenant bound coupon.
     * @return RedirectResponse|JsonResponse Updated coupon.
     */
    public function update(TenantCouponSaveRequest $request, Coupon $coupon): RedirectResponse|JsonResponse
    {
        return $this->saved($request, $this->service->save($request->validated(), $coupon), 'Coupon updated.');
    }

    /**
     * Set coupon availability explicitly, making repeated requests safe.
     *
     * @param  TenantCouponRequest  $request  Authorized status.
     * @param  Coupon  $coupon  Tenant bound coupon.
     * @return RedirectResponse|JsonResponse Updated coupon.
     */
    public function status(TenantCouponRequest $request, Coupon $coupon): RedirectResponse|JsonResponse
    {
        $this->service->change($coupon, (bool) $request->validated('is_active'));

        return $this->saved($request, $coupon->refresh(), 'Coupon status updated.');
    }

    /**
     * Soft delete a coupon while retaining usage and order references.
     *
     * @param  TenantCouponRequest  $request  Authorized request.
     * @param  Coupon  $coupon  Tenant bound coupon.
     * @return RedirectResponse|JsonResponse Deletion acknowledgement.
     */
    public function destroy(TenantCouponRequest $request, Coupon $coupon): RedirectResponse|JsonResponse
    {
        $this->service->change($coupon, null);

        return $this->saved($request, null, 'Coupon deleted.');
    }

    /**
     * Provide tenant layout context and permitted actions.
     *
     * @param  TenantCouponRequest  $request  Authorized request.
     * @return array<string, mixed> Page context.
     */
    private function viewData(TenantCouponRequest $request): array
    {
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        foreach (['create', 'update', 'delete'] as $action) {
            $data['can'.ucfirst($action)] = $this->roles->userHasPermission($data['tenantUser'], 'coupons.'.$action);
        }

        return $data;
    }

    /**
     * Return the existing API envelope or a form redirect.
     *
     * @param  TenantCouponRequest  $request  Current request.
     * @param  Coupon|null  $coupon  Saved record.
     * @param  string  $message  Success notification.
     * @param  int  $status  HTTP status.
     * @return RedirectResponse|JsonResponse Submission response.
     */
    private function saved(TenantCouponRequest $request, ?Coupon $coupon, string $message, int $status = 200): RedirectResponse|JsonResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $data = [];
            $data['message'] = $message;
            $data['data'] = $coupon;

            return response()->json($data, $status);
        }

        return redirect()->route('tenant.coupons.index')->with('success', $message);
    }
}
