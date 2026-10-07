<?php

namespace App\Services;

use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use App\Repositories\TenantOrderRepository;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantOrderService
{
    public function __construct(private readonly TenantOrderRepository $orders, private readonly TenantOrderCalculationService $calculator, private readonly TenantAuditLogService $audit, private readonly TenantCouponService $coupons) {}

    /**
     * Create or update a draft order and persist the validated pricing snapshot.
     *
     * @param  array  $input  The submitted order payload.
     * @param  User  $actor  The user performing the write.
     * @param  string  $source  The source channel such as admin or api.
     * @param  int|null  $id  The order identifier for updates.
     * @return Order The saved order model with related detail rows.
     */
    public function save(array $input, User $actor, string $source, ?int $id = null): Order
    {
        return DB::connection('tenant')->transaction(function () use ($input, $actor, $source, $id): Order {
            $order = $id ? $this->orders->find($id, true) : new Order;
            $before = $id ? $order->only(['status', 'grand_total']) : null;
            if ($id && $order->invoice()->exists()) {
                throw ValidationException::withMessages(['order' => 'An invoice snapshot exists. Order financial changes are blocked.']);
            }
            if ($id && $order->status !== 'draft') {
                throw ValidationException::withMessages(['order' => 'Only draft orders can be edited. Cancel and recreate a pending order if it needs changes.']);
            }
            $submitAs = $input['submit_as'] ?? 'draft';
            $expectedPricing = $input['expected_pricing_fingerprint'] ?? null;
            unset($input['idempotency_key'], $input['submit_as'], $input['expected_pricing_fingerprint']);
            $input = array_replace($order->draft_input ?? ['currency' => 'INR', 'shipping_amount' => '0.00', 'payment_method' => 'cod'], $input);
            TenantPaymentService::requireEnabled($input['payment_method']);
            $calculation = $this->calculator->calculate($input, true);
            if ($submitAs === 'confirmed' && $expectedPricing !== null && ! hash_equals($expectedPricing, $calculation['fingerprint'])) {
                throw ValidationException::withMessages(['items' => 'Prices or tax details changed since the preview. Review the updated totals before confirming.']);
            }
            $profile = $this->profile($input);
            $addresses = $this->addresses($input);
            $data = [
                ...$profile,
                ...$calculation['totals'],
                'pricing_fingerprint' => $calculation['fingerprint'],
                'draft_input' => $input,
                'currency' => 'INR',
                'customer_note' => $input['customer_note'] ?? null,
                'internal_note' => $input['internal_note'] ?? null,
            ];
            if (! $id) {
                $data += ['order_number' => 'ORD-'.Str::ulid(), 'order_date' => now(), 'source' => $source, 'status' => 'draft', 'payment_status' => 'unpaid', 'created_by' => $actor->id];
            }
            $this->orders->save($order, $data);
            $this->orders->replaceDetails($order, $calculation['items'], $addresses);
            $pendingPayment = $order->payments()->where('status', 'pending')->whereNull('reference_number')->whereNull('gateway')->first();
            $paymentBefore = $pendingPayment?->only(['status', 'method', 'amount', 'currency']);
            $payment = $order->payments()->updateOrCreate(['status' => 'pending', 'reference_number' => null, 'gateway' => null], ['method' => $input['payment_method'], 'amount' => $order->grand_total, 'currency' => 'INR', 'recorded_by' => $actor->id]);
            $this->audit->recordSnapshot($payment, $paymentBefore === null ? TenantAuditAction::CREATED : TenantAuditAction::PAYMENT_UPDATED, $paymentBefore, $payment->only(['status', 'method', 'amount', 'currency']), $actor);
            if (! $id) {
                $order->histories()->create(['from_status' => null, 'to_status' => 'draft', 'changed_by' => $actor->id, 'created_at' => now()]);
            }
            $this->audit->recordSnapshot($order, $id ? TenantAuditAction::UPDATED : TenantAuditAction::CREATED, $before, ['status' => 'draft', 'grand_total' => $order->grand_total], $actor);
            if ($submitAs !== 'draft') {
                $this->transition($order->id, $submitAs, $actor);
            }

            return $this->orders->details($order->id);
        }, 3);
    }

    /**
     * Resolve the customer profile values for a guest or existing customer order.
     *
     * @param  array  $input  The raw order input payload.
     * @return array The normalized customer profile data.
     */
    private function profile(array $input): array
    {
        if (! empty($input['customer_id'])) {
            $customer = $this->orders->customer((int) $input['customer_id']);

            return [
                'customer_id' => $customer->id,
                'customer_name' => trim($customer->first_name.' '.$customer->last_name),
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone ? trim($customer->phone_country_code.' '.$customer->phone) : null,
                'company_name' => $customer->company_name,
                'gstin' => $customer->gstin,
            ];
        }
        if (empty($input['customer_name']) || (empty($input['customer_email']) && empty($input['customer_phone']))) {
            throw ValidationException::withMessages(['customer_name' => 'Guest orders need a customer name and an email or phone number.']);
        }

        return [
            'customer_id' => null,
            ...Arr::only($input, ['customer_name', 'customer_email', 'customer_phone', 'company_name', 'gstin']),
            'customer_email' => $input['customer_email'] ?? null,
            'customer_phone' => $input['customer_phone'] ?? null,
            'company_name' => $input['company_name'] ?? null,
            'gstin' => $input['gstin'] ?? null,
        ];
    }

    /**
     * Normalize billing and shipping address payloads for order persistence.
     *
     * @param  array  $input  The raw order input payload.
     * @return array The address rows for both billing and shipping.
     */
    private function addresses(array $input): array
    {
        $billing = $input['billing'] ?? null;
        $shipping = ! empty($input['same_as_billing']) ? $billing : ($input['shipping'] ?? null);
        if (! $billing || ! $shipping) {
            throw ValidationException::withMessages(['shipping' => 'Supply both addresses or select same as billing.']);
        }

        return array_map(fn (string $type, array $address): array => ['type' => $type, ...$address, 'state_name' => config('customer_locations.IN.states.'.$address['state_code'])], ['billing', 'shipping'], [$billing, $shipping]);
    }

    /**
     * Advance an order through a valid status transition while applying inventory and history updates.
     *
     * @param  int  $id  The order identifier.
     * @param  string  $target  The target status.
     * @param  User  $actor  The user performing the transition.
     * @param  array  $data  Optional status metadata such as comments or shipment information.
     * @return Order The updated order record with details.
     */
    public function transition(int $id, string $target, User $actor, array $data = []): Order
    {
        return DB::connection('tenant')->transaction(function () use ($id, $target, $actor, $data): Order {
            $order = $this->orders->find($id, true);
            $from = $order->status;
            if ($target === 'cancelled' && $order->invoice()->where('status', 'issued')->exists()) {
                throw ValidationException::withMessages(['order' => 'An issued invoice exists. A cancellation or credit-note workflow is required and is not available in this release.']);
            }
            if ($order->paymentCheckout()->where('status', 'review')->exists()) {
                throw ValidationException::withMessages(['payment_status' => 'Resolve the payment review before changing fulfilment status.']);
            }
            if ($target === 'cancelled' && $order->paymentCheckout()->exists()) {
                throw ValidationException::withMessages(['order' => 'A gateway checkout is active for this order. Reconcile it before cancellation; gateway refunds are not supported.']);
            }
            if (! in_array($target, Order::TRANSITIONS[$from], true)) {
                throw ValidationException::withMessages(['status' => 'This order cannot move from '.$from.' to '.$target.'.']);
            }
            if ($target === 'delivered' && ($order->payment_status !== 'paid' || ! $this->captured($order)->isEqualTo($order->grand_total))) {
                throw ValidationException::withMessages(['payment_status' => 'Collect or verify the full payment before marking this order as delivered.']);
            }
            if ($target === 'cancelled' && $this->captured($order)->isGreaterThan('0')) {
                throw ValidationException::withMessages(['order' => 'This order has received funds. Cancellation requires a refund workflow, which is not available in this release.']);
            }
            if ($from === 'draft' && in_array($target, ['pending', 'confirmed'], true)) {
                $calculation = $this->calculator->calculate($order->draft_input, true);
                if (! hash_equals($order->pricing_fingerprint, $calculation['fingerprint'])) {
                    throw ValidationException::withMessages(['items' => 'Catalog prices or tax details changed. Reopen and save the draft to review updated totals before confirming.']);
                }
                $this->orders->save($order, $this->profile($order->draft_input));
                $this->coupons->redeem($order);
            }
            $items = $order->items()->get();
            if ($target === 'confirmed' && $order->shipping_tax_id !== null && ! $this->orders->tax((int) $order->shipping_tax_id)) {
                throw ValidationException::withMessages(['shipping_tax_id' => 'The shipping tax is no longer available. Update the draft, or cancel and recreate a pending order.']);
            }
            $stockAction = $target === 'confirmed' ? 'reserve' : ($target === 'shipped' ? 'ship' : ($target === 'cancelled' && in_array($from, ['confirmed', 'processing'], true) ? 'release' : null));
            if ($stockAction) {
                $variants = $this->orders->variants($items->pluck('product_variant_id')->all(), true, $stockAction !== 'reserve');
                foreach ($items->sortBy('product_variant_id') as $item) {
                    $variant = $variants->get($item->product_variant_id);
                    if (! $variant) {
                        throw ValidationException::withMessages(['items' => 'An inventory record is missing. Resolve it before changing status.']);
                    }
                    if ($stockAction === 'reserve' && (! $variant->status || ! $variant->product?->status || ! $variant->product?->tax?->is_available)) {
                        throw ValidationException::withMessages(['items' => 'Every ordered product, variant and tax must be available at confirmation.']);
                    }
                    if ($stockAction === 'reserve' && $variant->quantity - $variant->reserved_quantity < $item->quantity) {
                        throw ValidationException::withMessages(['items' => 'Insufficient available stock for '.$item->sku.'.']);
                    }
                    if ($stockAction !== 'reserve' && ($variant->reserved_quantity < $item->quantity || ($stockAction === 'ship' && $variant->quantity < $item->quantity))) {
                        throw ValidationException::withMessages(['items' => 'Inventory no longer matches the reservation. Resolve it before continuing.']);
                    }
                    $this->orders->stock($order, $variant, $stockAction, $stockAction === 'ship' ? -$item->quantity : 0, $stockAction === 'reserve' ? $item->quantity : -$item->quantity);
                }
            }
            if ($target === 'shipped') {
                $order->shipment()->create([...Arr::only($data, ['courier_name', 'tracking_number', 'tracking_url']), 'status' => 'shipped', 'shipped_at' => now()]);
            }
            if ($target === 'delivered') {
                $shipment = $order->shipment()->firstOrFail();
                $shipment->update(['status' => 'delivered', 'delivered_at' => now()]);
            }
            $fields = ['status' => $target];
            if ($target !== 'draft') {
                $fields['draft_input'] = null;
            }
            if ($target === 'cancelled') {
                $this->coupons->release($order);
                $fields += ['cancelled_at' => now(), 'cancellation_reason' => $data['comment'] ?? null];
            }
            $this->orders->save($order, $fields);
            $order->histories()->create(['from_status' => $from, 'to_status' => $target, 'comment' => $data['comment'] ?? null, 'changed_by' => $actor->id, 'created_at' => now()]);
            $this->audit->recordSnapshot($order, TenantAuditAction::STATUS_CHANGED, ['status' => $from], ['status' => $target], $actor);

            return $this->orders->details($id);
        }, 3);
    }

    /**
     * Sum the captured payment amounts recorded against an order.
     *
     * @param  Order  $order  The order whose payments should be totalled.
     * @return BigDecimal The captured amount.
     */
    public function captured(Order $order): BigDecimal
    {
        $total = TenantOrderCalculationService::money('0');
        foreach ($order->payments()->where('status', 'captured')->pluck('amount') as $amount) {
            $total = $total->plus((string) $amount);
        }

        return $total;
    }

    /**
     * Record a captured payment for an order and update the payment status.
     *
     * @param  int  $id  The order identifier.
     * @param  array  $data  The payment payload.
     * @param  User  $actor  The user recording the payment.
     * @return Order The updated order record with details.
     */
    public function payment(int $id, array $data, User $actor): Order
    {
        TenantPaymentService::requireEnabled($data['method']);

        return DB::connection('tenant')->transaction(function () use ($id, $data, $actor): Order {
            $order = $this->orders->find($id, true);
            if ($order->paymentCheckout()->exists()) {
                throw ValidationException::withMessages(['order' => 'An online checkout already owns this balance. Offline collection is not allowed.']);
            }
            if (in_array($order->status, ['draft', 'cancelled'], true)) {
                throw ValidationException::withMessages(['order' => 'Payments cannot be recorded against a draft or cancelled order.']);
            }
            if ($data['currency'] !== $order->currency) {
                throw ValidationException::withMessages(['currency' => 'Payment currency must match the order.']);
            }
            if ($order->payments()->where('method', $data['method'])->where('reference_number', $data['reference_number'])->exists()) {
                throw ValidationException::withMessages(['reference_number' => 'This receipt has already been recorded for this order.']);
            }
            $captured = $this->captured($order)->plus((string) $data['amount']);
            if ($captured->isGreaterThan($order->grand_total)) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds the outstanding order balance.']);
            }
            $payment = $order->payments()->create([...Arr::only($data, ['method', 'reference_number', 'amount', 'currency']), 'status' => 'captured', 'paid_at' => now(), 'recorded_by' => $actor->id]);
            $status = $captured->isEqualTo($order->grand_total) ? 'paid' : 'partially_paid';
            $this->orders->save($order, ['payment_status' => $status]);
            if ($status === 'paid') {
                $order->payments()->where('status', 'pending')->whereNull('gateway_payment_id')->update(['status' => 'superseded']);
            }
            $this->audit->recordSnapshot($payment, TenantAuditAction::CREATED, null, ['order_id' => $order->id, 'order_number' => $order->order_number, 'status' => $payment->status, 'method' => $payment->method, 'amount' => $payment->amount, 'payment_status' => $status], $actor);

            return $this->orders->details($id);
        }, 3);
    }
}
