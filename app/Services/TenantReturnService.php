<?php

namespace App\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderItem;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\ReturnReason;
use App\Models\Tenant\ReturnRequest;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantReturnService
{
    public function __construct(private readonly TenantReturnEligibilityService $eligibility, private readonly TenantRefundCalculationService $calculator, private readonly AuditLogService $audit) {}

    /** @param array<string, mixed> $input Validated customer request; prices and statuses are never accepted. */
    public function create(int $orderId, int $itemId, Customer $customer, array $input): ReturnRequest
    {
        return DB::connection('tenant')->transaction(function () use ($orderId, $itemId, $customer, $input): ReturnRequest {
            $order = Order::where('customer_id', $customer->id)->lockForUpdate()->findOrFail($orderId);
            $item = $order->items()->lockForUpdate()->findOrFail($itemId);
            $policy = $this->eligibility->check($item);
            if (! $policy['eligible']) {
                throw ValidationException::withMessages(['order_item' => $policy['reason']]);
            }
            ReturnReason::where('status', true)->findOrFail($input['reason_id']);
            if ((int) $input['quantity'] > $policy['remaining_returnable_quantity']) {
                throw ValidationException::withMessages(['quantity' => 'The quantity exceeds the remaining purchased quantity.']);
            }
            $calculation = $this->calculator->calculate($item, (int) $input['quantity']);
            if ($item->is_returnable === null) {
                $item->update(['is_returnable' => $item->product?->is_returnable ?? false, 'return_days' => $item->product?->return_days, 'is_replaceable' => $item->product?->is_replaceable ?? false, 'replacement_days' => $item->product?->replacement_days]);
            }
            $return = ReturnRequest::create([
                'return_number' => 'RET-' . now()->format('Ym') . '-' . Str::ulid(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'customer_id' => $customer->id,
                'quantity' => $input['quantity'],
                'reason_id' => $input['reason_id'],
                'reason_note' => $input['reason_note'] ?? null,
                'status' => 'requested',
                'requested_at' => now(),
                'refund_amount' => $calculation['total_amount'],
                'calculation' => $calculation,
            ]);
            $this->audit->recordSnapshot($return, 'requested', null, ['status' => 'requested', 'customer_id' => $customer->id, 'quantity' => $return->quantity]);

            return $return;
        }, 3);
    }

    /** @param array<string, mixed> $input Validated inspection and decision metadata. */
    public function transition(int $id, string $target, User $actor, array $input = []): ReturnRequest
    {
        return DB::connection('tenant')->transaction(function () use ($id, $target, $actor, $input): ReturnRequest {
            $orderId = ReturnRequest::findOrFail($id)->order_id;
            Order::lockForUpdate()->findOrFail($orderId);
            $return = ReturnRequest::lockForUpdate()->findOrFail($id);
            $from = $return->status;
            if ($from === 'inspection_passed' && $target === 'closed' && ! TenantOrderCalculationService::money($return->refund_amount)->isZero()) {
                throw ValidationException::withMessages(['status' => 'Complete the refund before closing this return.']);
            }
            if (! in_array($target, ReturnRequest::TRANSITIONS[$from] ?? [], true) || in_array($target, ['refund_processing', 'refunded', 'refund_failed'], true)) {
                throw ValidationException::withMessages(['status' => 'This return cannot move from ' . $from . ' to ' . $target . '.']);
            }
            if (in_array($target, ['rejected', 'inspection_failed'], true) && empty($input['rejection_reason'])) {
                throw ValidationException::withMessages(['rejection_reason' => 'A rejection or failed inspection requires a reason.']);
            }
            $fields = ['status' => $target];
            if (isset($input['admin_note'])) {
                $fields['admin_note'] = $input['admin_note'];
            }
            if (isset($input['rejection_reason'])) {
                $fields['rejection_reason'] = $input['rejection_reason'];
            }
            $action = match ($target) {
                'approved' => 'approved',
                'rejected' => 'rejected',
                'received' => 'received',
                'inspection_passed', 'inspection_failed' => 'inspected',
                'closed', 'cancelled' => 'completed',
                default => null
            };
            if ($action) {
                $fields[$action . '_at'] = now();
                $fields[$action . '_by'] = $actor->id;
            }
            if ($target === 'inspection_failed') {
                $fields['inventory_disposition'] = 'inspection_failed';
            }
            if ($target === 'inspection_passed') {
                $disposition = $input['inventory_disposition'] ?? 'do_not_restock';
                if (! in_array($disposition, ['restock', 'damaged', 'defective', 'quarantine', 'do_not_restock'], true)) {
                    throw ValidationException::withMessages(['inventory_disposition' => 'Select a valid inventory disposition.']);
                }
                $fields['inventory_disposition'] = $disposition;
                if ($disposition === 'restock') {
                    $item = OrderItem::findOrFail($return->order_item_id);
                    $variant = ProductVariant::withTrashed()->lockForUpdate()->findOrFail($item->product_variant_id);
                    $variant->increment('quantity', $return->quantity);
                    DB::connection('tenant')->table('return_stock_movements')->insert(['return_request_id' => $return->id, 'product_variant_id' => $variant->id, 'quantity_delta' => $return->quantity, 'quantity_after' => $variant->quantity, 'created_at' => now()]);
                }
            }
            $return->update($fields);
            $this->audit->recordSnapshot($return, $target, ['status' => $from], [...$fields, 'actor_id' => $actor->id]);

            return $return;
        }, 3);
    }
}
