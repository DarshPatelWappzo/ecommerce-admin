<?php

namespace App\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Repositories\CustomerShoppingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CustomerOrderService
{
    public function __construct(
        private readonly CustomerShoppingRepository $shopping,
        private readonly CustomerCartService $carts,
        private readonly TenantPaymentService $payments,
        private readonly TenantReturnEligibilityService $returns,
        private readonly TenantReplacementEligibilityService $replacements,
        private readonly TenantOrderService $orders,
    ) {}

    public function list(Customer $customer, array $filters): LengthAwarePaginator
    {
        $query = $this->shopping->orders($customer)->with(['returns.refund', 'payments', 'shipment', 'invoice', 'paymentCheckout']);
        if (! empty($filters['search'])) {
            $query->whereLike('order_number', '%' . $filters['search'] . '%');
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $query->where('order_date', '>=', $filters['from'] . ' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('order_date', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return $query->latest('id')->paginate($filters['per_page'] ?? 15)->withQueryString()->through(fn(Order $order): array => $this->data($order));
    }

    public function detail(Customer $customer, int $id): array
    {
        $order = $this->shopping->order($customer, $id);
        $data = $this->data($order);
        $data['addresses'] = $order->addresses->map->only(['type', 'name', 'phone', 'address_line_1', 'address_line_2', 'city', 'state_name', 'state_code', 'country_code', 'postal_code']);
        $data['customer_note'] = $order->customer_note;
        $data['items'] = $order->items->map(function ($item) use ($order): array {
            $item->setRelation('order', $order);

            return [
                ...$item->only(['id', 'product_id', 'product_variant_id', 'product_name', 'variant_name', 'sku', 'hsn_code', 'quantity', 'unit_price', 'subtotal', 'discount_amount', 'coupon_discount', 'taxable_amount', 'tax_rate', 'tax_name', 'tax_code', 'tax_amount', 'total_amount', 'is_returnable', 'return_days', 'is_replaceable', 'replacement_days']),
                'return_eligibility' => $this->returns->check($item),
                'replacement_eligibility' => $this->replacements->check($item),
                'return_path' => route('api.customer.returns.store', ['order' => $order->id, 'item' => $item->id], false),
                'replacement_path' => route('api.customer.replacements.store', ['order' => $order->id, 'item' => $item->id], false)
            ];
        });

        return $data;
    }

    public function data(Order $order): array
    {
        $method = $order->payments->first()?->method;
        $review = $order->paymentCheckout?->status === 'review';
        $refunds = $order->returns->pluck('refund')->filter();
        $refunded = TenantOrderCalculationService::money('0');
        foreach ($refunds->where('status', 'processed') as $refund) {
            $refunded = $refunded->plus($refund->amount);
        }
        $refundStatus = match (true) {
            $refunded->isGreaterThanOrEqualTo($order->grand_total) && $refunded->isGreaterThan(0) => 'refunded',
            $refunded->isGreaterThan(0) => 'partially_refunded',
            $refunds->contains('status', 'processing') => 'processing',
            $refunds->contains('status', 'pending') => 'pending',
            $refunds->contains('status', 'failed') => 'failed',
            default => 'none',
        };
        $state = match (true) {
            $review => 'payment_review_required',
            $refunded->isGreaterThan(0) => $refundStatus,
            $order->payment_status === 'paid' => 'captured',
            $order->payment_status === 'refunded' => 'refunded',
            $method === 'cod' => 'cod_pending_collection',
            in_array($method, ['cash', 'bank_transfer'], true) => 'offline_pending_verification',
            default => $order->payment_status,
        };

        return [
            ...$order->only(['id', 'order_number', 'order_date', 'status', 'payment_status', 'currency', 'subtotal', 'discount_total', 'coupon_code', 'coupon_discount', 'shipping_amount', 'shipping_tax_amount', 'tax_total', 'rounding_adjustment', 'grand_total']),
            'payment_method' => $method,
            'payment_state' => $state,
            'refund_status' => $refundStatus,
            'refunded_amount' => (string) $refunded,
            'can_retry_payment' => ! $review && $method === 'razorpay' && in_array($order->status, ['pending', 'confirmed', 'processing'], true) && in_array($order->payment_status, ['unpaid', 'pending', 'failed'], true) && array_key_exists('razorpay', TenantPaymentService::methods()),
            'shipment' => $order->shipment?->only(['status', 'courier_name', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at']),
            'invoice' => $order->invoice?->status === 'issued' ? ['number' => $order->invoice->number, 'path' => route('api.customer.orders.invoice', $order->id, false)] : null,
        ];
    }

    /** Reconcile first; retries always reuse the existing durable gateway checkout. */
    public function payment(Customer $customer, int $id, array $input): array
    {
        $order = $this->shopping->order($customer, $id);
        if ($order->payments->first()?->method !== 'razorpay') {
            throw ValidationException::withMessages(['payment' => 'This order uses an offline payment method.']);
        }
        $tenantId = $customer->currentAccessToken()->tenant_database_id;
        $action = $input['action'] ?? 'initiate';
        if ($action === 'verify') {
            $this->payments->verify($id, $input, $tenantId);
        } else {
            $this->payments->recover($id, $tenantId);
        }
        $order = $this->shopping->order($customer, $id);
        $checkout = null;
        if ($action === 'initiate' && $order->payment_status !== 'paid') {
            if (! $this->data($order)['can_retry_payment']) {
                throw ValidationException::withMessages(['payment' => 'This order is not eligible for payment retry.']);
            }
            $checkout = $this->payments->initiate($id, $customer, $tenantId);
        }
        $this->carts->view($customer);

        return ['order' => $this->detail($customer, $id), 'checkout' => $checkout];
    }

    /** Reorder atomically so unavailable lines never leave a partially populated cart. */
    public function reorder(Customer $customer, int $id): array
    {
        return $this->shopping->transaction($customer, function () use ($customer, $id): array {
            $order = $this->shopping->order($customer, $id);
            foreach ($order->items as $item) {
                if (! $item->product_id || ! $item->product_variant_id) {
                    throw ValidationException::withMessages(['items' => 'An ordered product is no longer available.']);
                }
                $this->carts->add($customer, $item->only(['product_id', 'product_variant_id', 'quantity']));
            }

            return $this->carts->describe($this->carts->active($customer));
        });
    }

    public function invoicePayment(Order $order): array
    {
        $received = $this->orders->captured($order);

        return [
            'status' => $order->payment_status,
            'received_amount' => (string) $received,
            'outstanding_amount' => (string) TenantOrderCalculationService::money($order->grand_total)->minus($received),
            'as_of' => now()->toIso8601String(),
            'records' => $order->payments->where('status', 'captured')
        ];
    }
}
