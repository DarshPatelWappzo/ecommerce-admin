<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerWishlistRequest;
use App\Services\CustomerWishlistService;
use Illuminate\Http\JsonResponse;

class CustomerWishlistController extends Controller
{
    public function __construct(private readonly CustomerWishlistService $wishlists) {}

    /** List saved products with current catalog data.
     * @param  CustomerWishlistRequest  $request  Validated pagination.
     * @return JsonResponse Paginated wishlist.
     */
    public function index(CustomerWishlistRequest $request): JsonResponse
    {
        $data = $this->wishlists->list($request->user(), $request->validated());

        return response()->json($data);
    }

    /** Save a product once per customer.
     * @param  CustomerWishlistRequest  $request  Validated product.
     * @return JsonResponse Saved acknowledgement.
     */
    public function store(CustomerWishlistRequest $request): JsonResponse
    {
        $this->wishlists->add($request->user(), $request->validated('product_id'));
        $data = ['message' => 'Product saved.'];

        return response()->json($data, 201);
    }

    /** Remove an owned saved product.
     * @param  CustomerWishlistRequest  $request  Authenticated customer.
     * @param  int  $product  Saved product identifier.
     * @return JsonResponse Removal acknowledgement.
     */
    public function destroy(CustomerWishlistRequest $request, int $product): JsonResponse
    {
        $this->wishlists->remove($request->user(), $product);
        $data = ['message' => 'Product removed.'];

        return response()->json($data);
    }

    /** Move a saved product only after cart validation succeeds.
     * @param  CustomerWishlistRequest  $request  Validated variant and quantity.
     * @param  int  $product  Saved product identifier.
     * @return JsonResponse Updated cart.
     */
    public function move(CustomerWishlistRequest $request, int $product): JsonResponse
    {
        $data = ['data' => $this->wishlists->move($request->user(), $product, $request->validated())];

        return response()->json($data);
    }
}
