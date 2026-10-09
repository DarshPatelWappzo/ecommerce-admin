<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerCartRequest;
use App\Services\CustomerCartService;
use Illuminate\Http\JsonResponse;

class CustomerCartController extends Controller
{
    public function __construct(private readonly CustomerCartService $carts) {}

    /** View the owned cart with current prices and availability.
     * @param  CustomerCartRequest  $request  Authenticated customer.
     * @return JsonResponse Cart envelope.
     */
    public function show(CustomerCartRequest $request): JsonResponse
    {
        $data = ['data' => $this->carts->view($request->user())];

        return response()->json($data);
    }

    /** Add or merge an owned cart line without reserving stock.
     * @param  CustomerCartRequest  $request  Validated item.
     * @return JsonResponse Current cart.
     */
    public function store(CustomerCartRequest $request): JsonResponse
    {
        $data = ['data' => $this->carts->mutate($request->user(), 'add', $request->validated())];

        return response()->json($data, 201);
    }

    /** Set or adjust a line quantity.
     * @param  CustomerCartRequest  $request  Validated quantity.
     * @param  int  $item  Owned line identifier.
     * @return JsonResponse Current cart.
     */
    public function update(CustomerCartRequest $request, int $item): JsonResponse
    {
        $data = ['data' => $this->carts->mutate($request->user(), 'update', $request->validated(), $item)];

        return response()->json($data);
    }

    /** Remove an owned line.
     * @param  CustomerCartRequest  $request  Authenticated customer.
     * @param  int  $item  Owned line identifier.
     * @return JsonResponse Current cart.
     */
    public function destroy(CustomerCartRequest $request, int $item): JsonResponse
    {
        $data = ['data' => $this->carts->mutate($request->user(), 'remove', id: $item)];

        return response()->json($data);
    }

    /** Clear the active cart and its coupon.
     * @param  CustomerCartRequest  $request  Authenticated customer.
     * @return JsonResponse Empty cart.
     */
    public function clear(CustomerCartRequest $request): JsonResponse
    {
        $data = ['data' => $this->carts->mutate($request->user(), 'clear')];

        return response()->json($data);
    }
}
