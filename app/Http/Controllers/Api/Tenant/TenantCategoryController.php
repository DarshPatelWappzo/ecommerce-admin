<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\TenantApiCategoryRequest;
use App\Repositories\TenantCategoryRepository;
use Illuminate\Http\JsonResponse;

class TenantCategoryController extends Controller
{
    /**
     * Initialize category persistence for the tenant selected by the API token.
     *
     * @param  TenantCategoryRepository  $categories  The tenant category repository.
     */
    public function __construct(private readonly TenantCategoryRepository $categories) {}

    /**
     * List categories with parent names and pagination.
     *
     * @param  TenantApiCategoryRequest  $request  The authorized listing request.
     * @return JsonResponse The current page of categories.
     */
    public function index(TenantApiCategoryRequest $request): JsonResponse
    {
        $data = $this->categories->paginate();

        return response()->json($data);
    }

    /**
     * Return parent options for adding a category.
     *
     * @param  TenantApiCategoryRequest  $request  The authorized creation request.
     * @return JsonResponse The available parent categories.
     */
    public function create(TenantApiCategoryRequest $request): JsonResponse
    {
        $data = [];
        $data['parent_categories'] = $this->categories->parentOptions();

        return response()->json($data);
    }

    /**
     * Create a category with an automatically generated slug.
     *
     * @param  TenantApiCategoryRequest  $request  The validated category fields.
     * @return JsonResponse The created category and success message.
     */
    public function store(TenantApiCategoryRequest $request): JsonResponse
    {
        $id = $this->categories->save($request->validated());
        $data = [];
        $data['message'] = 'Category created successfully.';
        $data['data'] = $this->categories->find($id);

        return response()->json($data, 201);
    }

    /**
     * Return the requested tenant category.
     *
     * @param  TenantApiCategoryRequest  $request  The authorized viewing request.
     * @param  int  $category  The category identifier in the current tenant.
     * @return JsonResponse The category details.
     */
    public function show(TenantApiCategoryRequest $request, int $category): JsonResponse
    {
        $data = [];
        $data['data'] = $this->categories->find($category);

        return response()->json($data);
    }

    /**
     * Return category details and valid parent options for editing.
     *
     * @param  TenantApiCategoryRequest  $request  The authorized editing request.
     * @param  int  $category  The category identifier in the current tenant.
     * @return JsonResponse The category and parent choices excluding descendants.
     */
    public function edit(TenantApiCategoryRequest $request, int $category): JsonResponse
    {
        $data = [];
        $data['data'] = $this->categories->find($category);
        $data['parent_categories'] = $this->categories->parentOptions($category);

        return response()->json($data);
    }

    /**
     * Update category fields and regenerate the slug.
     *
     * @param  TenantApiCategoryRequest  $request  The validated category fields.
     * @param  int  $category  The category identifier in the current tenant.
     * @return JsonResponse The saved category and success message.
     */
    public function update(TenantApiCategoryRequest $request, int $category): JsonResponse
    {
        $this->categories->save($request->validated(), $category);
        $data = [];
        $data['message'] = 'Category updated successfully.';
        $data['data'] = $this->categories->find($category);

        return response()->json($data);
    }
}
