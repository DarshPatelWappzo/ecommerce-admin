<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantProductRequest;
use App\Http\Requests\TenantProductSaveRequest;
use App\Repositories\TenantProductRepository;
use App\Services\TenantProductService;
use Illuminate\Http\JsonResponse;

class TenantProductController extends Controller
{
    /**
     * Initialize product module dependencies.
     *
     * @param  TenantProductRepository  $products  The products for this action.
     * @param  TenantProductService  $service  The service for this action.
     */
    public function __construct(private readonly TenantProductRepository $products, private readonly TenantProductService $service) {}

    /**
     * List records for the current tenant.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return JsonResponse The action result.
     */
    public function index(TenantProductRequest $request): JsonResponse
    {
        $data = $this->products->paginate($request->validated());

        return response()->json($data);
    }

    /**
     * Return the product creation form and catalog choices.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @return JsonResponse The action result.
     */
    public function create(TenantProductRequest $request): JsonResponse
    {
        $data = $this->products->options();

        return response()->json($data);
    }

    /**
     * Return the requested tenant product.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse The action result.
     */
    public function show(TenantProductRequest $request, int $product): JsonResponse
    {
        $data = [];
        $data['data'] = $this->products->find($product);

        return response()->json($data);
    }

    /**
     * Return the product editing data.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse The action result.
     */
    public function edit(TenantProductRequest $request, int $product): JsonResponse
    {
        $record = $this->products->find($product);
        $data = $this->products->options($record);
        $data['data'] = $record;

        return response()->json($data);
    }

    /**
     * Create a validated product and its variants.
     *
     * @param  TenantProductSaveRequest  $request  The request for this action.
     * @return JsonResponse The action result.
     */
    public function store(TenantProductSaveRequest $request): JsonResponse
    {
        $data = [];
        $data['message'] = 'Product created successfully.';
        $data['data'] = $this->service->save($request->validated());

        return response()->json($data, 201);
    }

    /**
     * Save a validated product and its related records.
     *
     * @param  TenantProductSaveRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse The action result.
     */
    public function update(TenantProductSaveRequest $request, int $product): JsonResponse
    {
        $data = [];
        $data['message'] = 'Product updated successfully.';
        $data['data'] = $this->service->save($request->validated(), $product);

        return response()->json($data);
    }

    /**
     * Soft delete a product without reserved stock.
     *
     * @param  TenantProductRequest  $request  The request for this action.
     * @param  int  $product  The product for this action.
     * @return JsonResponse The action result.
     */
    public function destroy(TenantProductRequest $request, int $product): JsonResponse
    {
        $this->service->delete($product);
        $data = [];
        $data['message'] = 'Product deleted successfully.';

        return response()->json($data);
    }
}
