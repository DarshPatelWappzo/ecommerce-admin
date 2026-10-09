<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerShoppingRequest;
use App\Http\Resources\CustomerProductResource;
use App\Models\Tenant\Category;
use App\Repositories\CustomerShoppingRepository;
use Illuminate\Http\JsonResponse;

class CustomerCatalogController extends Controller
{
    public function __construct(private readonly CustomerShoppingRepository $shopping) {}

    /** List active customer catalog products.
     * @param  CustomerShoppingRequest  $request  Validated search and category.
     * @return JsonResponse Paginated public catalog fields.
     */
    public function index(CustomerShoppingRequest $request): JsonResponse
    {
        $query = $this->shopping->products();
        if ($request->validated('search')) {
            $query->whereLike('name', '%' . $request->validated('search') . '%');
        }
        if ($request->validated('category_id')) {
            $query->whereHas('categories', fn($categories) => $categories->whereKey($request->validated('category_id'))->where('status', true));
        }
        $data = $query->latest('id')->paginate($request->validated('per_page', 15))->withQueryString()
            ->through(fn($product): array => (new CustomerProductResource($product))->resolve($request));

        return response()->json($data);
    }

    /** Read an active product by ID or slug with variants.
     * @param  CustomerShoppingRequest  $request  Authenticated customer.
     * @param  string  $product  Product ID or slug.
     * @return JsonResponse Safe product details.
     */
    public function show(CustomerShoppingRequest $request, string $product): JsonResponse
    {
        $record = $this->shopping->products()->where(fn($query) => $query->where('slug', $product)->when(ctype_digit($product), fn($query) => $query->orWhereKey((int) $product)))->firstOrFail();
        $data = ['data' => (new CustomerProductResource($record))->resolve($request)];

        return response()->json($data);
    }

    /** List active category choices.
     * @param  CustomerShoppingRequest  $request  Validated pagination.
     * @return JsonResponse Paginated categories.
     */
    public function categories(CustomerShoppingRequest $request): JsonResponse
    {
        $data = Category::where('status', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($request->validated('per_page', 15), ['id', 'name', 'slug', 'parent_id'])
            ->withQueryString();

        return response()->json($data);
    }
}
