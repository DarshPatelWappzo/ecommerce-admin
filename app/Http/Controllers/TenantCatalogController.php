<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantCatalogRequest;
use App\Http\Requests\TenantProductRequest;
use App\Repositories\TenantCatalogRepository;
use App\Services\TenantCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class TenantCatalogController extends Controller
{
    /**
     * Initialize product module dependencies.
     *
     * @param  TenantCatalogRepository  $catalog  The catalog for this action.
     * @param  TenantCatalogService  $service  The service for this action.
     */
    public function __construct(private readonly TenantCatalogRepository $catalog, private readonly TenantCatalogService $service) {}

    /**
     * List records for the current tenant.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return View|JsonResponse The action result.
     */
    public function index(TenantProductRequest $request): View|JsonResponse
    {
        $data = $this->catalog->all();
        if ($request->is('api/*')) {
            return response()->json($data);
        }
        $data['tenantUser'] = $request->user('tenant');
        $data['tenantDomain'] = session('tenant_domain');

        return view('tenant.products.catalog', $data);
    }

    /**
     * Save a reusable tenant product tag.
     *
     * @param  TenantCatalogRequest  $request  The request for this action.
     * @return JsonResponse The action result.
     */
    public function saveTag(TenantCatalogRequest $request): JsonResponse
    {
        $data = [];
        $data['data'] = $this->service->save($request->validated(), true);
        $data['message'] = 'Tag saved successfully.';

        return response()->json($data);
    }

    /**
     * Save an attribute and its available options.
     *
     * @param  TenantCatalogRequest  $request  The request for this action.
     * @return JsonResponse The action result.
     */
    public function saveAttribute(TenantCatalogRequest $request): JsonResponse
    {
        $data = [];
        $data['data'] = $this->service->save($request->validated(), false);
        $data['message'] = 'Attribute saved successfully.';

        return response()->json($data);
    }
}
