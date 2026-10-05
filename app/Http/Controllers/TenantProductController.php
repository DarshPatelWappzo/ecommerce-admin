<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantProductRequest;
use App\Http\Requests\TenantProductSaveRequest;
use App\Repositories\TenantProductRepository;
use App\Repositories\TenantRoleRepository;
use App\Services\TenantProductService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class TenantProductController extends Controller
{
    /**
     * Initialize product module dependencies.
     *
     * @param  TenantProductRepository  $products  The products for this action.
     * @param  TenantProductService  $service  The service for this action.
     * @param  TenantRoleRepository  $roles  The roles for this action.
     */
    public function __construct(
        private readonly TenantProductRepository $products,
        private readonly TenantProductService $service,
        private readonly TenantRoleRepository $roles,
    ) {}

    /**
     * List records for the current tenant.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return View The action result.
     */
    public function index(TenantProductRequest $request): View
    {
        $data = $this->viewData($request);
        $data['products'] = $this->products->paginate($request->validated());
        $data['catalog'] = $this->products->filterOptions();
        $data['filters'] = $request->validated();

        return view('tenant.products.index', $data);
    }

    /**
     * Return the product creation form and catalog choices.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return View The action result.
     */
    public function create(TenantProductRequest $request): View
    {
        $data = $this->viewData($request);
        $data['product'] = null;
        $data['catalog'] = $this->products->options();

        return view('tenant.products.form', $data);
    }

    /**
     * Return the product editing data.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return View The action result.
     */
    public function edit(TenantProductRequest $request, int $product): View
    {
        $data = $this->viewData($request);
        $data['product'] = $this->products->find($product);
        $data['catalog'] = $this->products->options($data['product']);

        return view('tenant.products.form', $data);
    }

    /**
     * Create a validated product and its variants.
     *
     * @param  TenantProductSaveRequest  $request  The request for this action.
     * @return JsonResponse|RedirectResponse The action result.
     */
    public function store(TenantProductSaveRequest $request): JsonResponse|RedirectResponse
    {
        $this->service->save($request->validated());

        return $this->saved($request, 'Product created successfully.', 201);
    }

    /**
     * Save a validated product and its related records.
     *
     * @param  TenantProductSaveRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse|RedirectResponse The action result.
     */
    public function update(TenantProductSaveRequest $request, int $product): JsonResponse|RedirectResponse
    {
        $this->service->save($request->validated(), $product);

        return $this->saved($request, 'Product updated successfully.');
    }

    /**
     * Soft delete a product without reserved stock.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse|RedirectResponse The action result.
     */
    public function destroy(TenantProductRequest $request, int $product): JsonResponse|RedirectResponse
    {
        $this->service->delete($product);

        return $this->saved($request, 'Product deleted successfully.');
    }

    /**
     * Return a successful form response.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  string  $message  The message for this action.
     * @param  int  $status  The status for this action.
     * @return JsonResponse|RedirectResponse The action result.
     */
    private function saved(TenantProductRequest $request, string $message, int $status = 200): JsonResponse|RedirectResponse
    {
        $request->session()->flash('success', $message);
        if ($request->expectsJson()) {
            $data = [];
            $data['message'] = $message;
            $data['redirect'] = route('tenant.products.index');

            return response()->json($data, $status);
        }

        return redirect()->route('tenant.products.index');
    }

    /**
     * Build shared tenant view data and action permissions.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return array The action result.
     */
    private function viewData(TenantProductRequest $request): array
    {
        $data = [];
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');
        foreach (['create', 'update', 'delete'] as $action) {
            $data['can' . ucfirst($action)] = $this->roles->userHasPermission($data['tenantUser'], 'products.' . $action);
        }

        return $data;
    }
}
