<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerCheckoutRequest;
use App\Http\Resources\TenantCustomerAddressResource;
use App\Services\CustomerCheckoutService;
use App\Services\CustomerOrderService;
use Illuminate\Http\JsonResponse;

class CustomerCheckoutController extends Controller
{
    public function __construct(private readonly CustomerCheckoutService $checkout, private readonly CustomerOrderService $orders) {}

    /** Preview server prices, stock, owned address snapshots and payment methods.
     * @param  CustomerCheckoutRequest  $request  Validated selections.
     * @return JsonResponse Checkout summary and pricing fingerprint.
     */
    public function summary(CustomerCheckoutRequest $request): JsonResponse
    {
        $data = ['data' => $this->checkout->summary($request->user(), $request->validated())];

        return response()->json($data);
    }

    /** List owned saved addresses for checkout selection.
     * @param  CustomerCheckoutRequest  $request  Authenticated customer.
     * @return JsonResponse Paginated address choices.
     */
    public function addresses(CustomerCheckoutRequest $request): JsonResponse
    {
        $data = $request->user()->addresses()->paginate($request->validated('per_page', 15))->withQueryString()
            ->through(fn($address): array => (new TenantCustomerAddressResource($address))->resolve($request));

        return response()->json($data);
    }

    /** Persist a validated coupon on the active cart.
     * @param  CustomerCheckoutRequest  $request  Validated coupon.
     * @return JsonResponse Updated calculation.
     */
    public function applyCoupon(CustomerCheckoutRequest $request): JsonResponse
    {
        $data = ['data' => $this->checkout->coupon($request->user(), $request->validated())];

        return response()->json($data);
    }

    /** Remove the active cart coupon.
     * @param  CustomerCheckoutRequest  $request  Validated selections.
     * @return JsonResponse Updated calculation.
     */
    public function removeCoupon(CustomerCheckoutRequest $request): JsonResponse
    {
        $data = ['data' => $this->checkout->coupon($request->user(), $request->validated(), true)];

        return response()->json($data);
    }

    /** Commit one confirmed order and then initiate its recoverable payment.
     * @param  CustomerCheckoutRequest  $request  Validated placement and idempotency key.
     * @return JsonResponse Owned order and optional gateway checkout.
     */
    public function place(CustomerCheckoutRequest $request): JsonResponse
    {
        $id = $this->checkout->place($request->user(), $request->validated());
        $order = $this->orders->detail($request->user(), $id);
        $data = ['data' => $order['payment_method'] === 'razorpay' && $order['payment_status'] !== 'paid'
            ? $this->orders->payment($request->user(), $id, ['action' => 'initiate'])
            : ['order' => $order, 'checkout' => null]];

        return response()->json($data, 201);
    }
}
