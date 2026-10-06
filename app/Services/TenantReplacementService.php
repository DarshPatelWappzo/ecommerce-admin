<?php

namespace App\Services;

use App\Mail\ReplacementStatusChanged;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Order;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\ReplacementRequest;
use App\Models\Tenant\ReturnReason;
use App\Models\Tenant\ReturnRequest;
use App\Models\Tenant\User;
use App\Repositories\TenantOrderRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantReplacementService
{
    public function __construct(private readonly TenantReplacementEligibilityService $eligibility, private readonly TenantOrderRepository $orders, private readonly TenantRefundCalculationService $calculator, private readonly AuditLogService $audit) {}

    /** @param array<string, mixed> $input Validated reason and quantity, never a substitute SKU. */
    public function create(int $orderId, int $itemId, Customer $customer, array $input, ?User $actor = null): ReplacementRequest
    {
        return DB::connection('tenant')->transaction(function () use ($orderId, $itemId, $customer, $input, $actor): ReplacementRequest {
            $order = Order::where('customer_id', $customer->id)->lockForUpdate()->findOrFail($orderId);
            $item = $order->items()->lockForUpdate()->findOrFail($itemId);
            $policy = $this->eligibility->check($item);
            if (! $policy['eligible']) {
                throw ValidationException::withMessages(['order_item' => $policy['reason']]);
            }
            $quantity = (int) $input['quantity'];
            if ($quantity < 1 || $quantity > $policy['remaining_replaceable_quantity']) {
                throw ValidationException::withMessages(['quantity' => 'The quantity exceeds the remaining eligible quantity.']);
            }
            ReturnReason::where('status', true)->findOrFail($input['reason_id']);
            $record = ReplacementRequest::create([
                'replacement_number' => 'REP-' . now()->format('Ym') . '-' . Str::ulid(),
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'customer_id' => $customer->id,
                'quantity' => $quantity,
                'reason_id' => $input['reason_id'],
                'reason_note' => $input['reason_note'] ?? null,
                'status' => 'requested',
                'requested_at' => now(),
                'policy_snapshot' => $policy,
            ]);
            $this->recordChange($record, null, $actor ?? $customer);

            return $record;
        }, 3);
    }

    /** @param array<string, mixed> $input Validated inspection, tracking and staff notes. */
    public function transition(int $id, string $target, User|Customer $actor, array $input = []): ReplacementRequest
    {
        return DB::connection('tenant')->transaction(function () use ($id, $target, $actor, $input): ReplacementRequest {
            $lookup = ReplacementRequest::query();
            if ($actor instanceof Customer) {
                $lookup->where('customer_id', $actor->id);
            }
            $orderId = $lookup->findOrFail($id)->order_id;
            Order::lockForUpdate()->findOrFail($orderId);
            $record = ReplacementRequest::lockForUpdate()->findOrFail($id);
            $from = $record->status;
            if ($actor instanceof Customer && ($target !== 'cancelled' || ! in_array($from, ['requested', 'approved'], true))) {
                throw ValidationException::withMessages(['status' => 'Cancellation is only available before pickup.']);
            }
            if ($target === 'converted_to_refund' || ! in_array($target, ReplacementRequest::TRANSITIONS[$from] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This replacement cannot move from ' . $from . ' to ' . $target . '.']);
            }
            if (in_array($target, ['rejected', 'qc_failed'], true) && empty($input['admin_note'])) {
                throw ValidationException::withMessages(['admin_note' => 'Explain the rejection or failed inspection.']);
            }
            $fields = Arr::only($input, ['admin_note']);
            if ($target === 'qc_passed') {
                $disposition = $input['inventory_disposition'] ?? 'do_not_restock';
                if (! in_array($disposition, ReplacementRequest::DISPOSITIONS, true)) {
                    throw ValidationException::withMessages(['inventory_disposition' => 'Select a valid inventory disposition.']);
                }
                $fields['inventory_disposition'] = $disposition;
                if ($disposition === 'restock') {
                    $variant = $this->variant($record);
                    if (! $variant || $variant->trashed() || $variant->sku !== $record->item->sku) {
                        throw ValidationException::withMessages(['inventory_disposition' => 'The original SKU is unavailable for restocking.']);
                    }
                    $this->stock($record, $variant, 'restock', $record->quantity, 0);
                }
            }
            if ($target === 'qc_failed') {
                $fields['inventory_disposition'] = 'defective';
            }
            if ($target === 'replacement_processing') {
                $variant = $this->variant($record);
                if (! $variant || $variant->trashed() || ! $variant->status || $variant->sku !== $record->item->sku || ! $variant->product?->status || $variant->quantity - $variant->reserved_quantity < $record->quantity) {
                    $target = 'out_of_stock';
                } else {
                    $this->stock($record, $variant, 'reserve', 0, $record->quantity);
                    $fields['stock_reserved'] = true;
                }
            }
            if ($target === 'shipped') {
                if (empty($input['courier_name']) || empty($input['tracking_number'])) {
                    throw ValidationException::withMessages(['tracking_number' => 'Courier and tracking number are required before dispatch.']);
                }
                $variant = $this->variant($record);
                if (! $record->stock_reserved || ! $variant || $variant->trashed() || $variant->sku !== $record->item->sku || $variant->reserved_quantity < $record->quantity || $variant->quantity < $record->quantity) {
                    throw ValidationException::withMessages(['stock' => 'Reserved stock is unavailable. Resolve inventory before dispatch.']);
                }
                $record->shipment()->create([...Arr::only($input, ['courier_name', 'tracking_number', 'tracking_url']), 'order_id' => $record->order_id, 'shipment_key' => 'replacement-' . $record->id, 'status' => 'shipped', 'shipped_at' => now()]);
                $this->stock($record, $variant, 'ship', -$record->quantity, -$record->quantity);
                $fields['stock_reserved'] = false;
            }
            if ($target === 'delivered') {
                $record->shipment()->firstOrFail()->update(['status' => 'delivered', 'delivered_at' => now()]);
            }
            $timestamp = match ($target) {
                'qc_passed', 'qc_failed' => 'qc_completed_at',
                'replacement_processing' => 'processing_at',
                default => $target . '_at',
            };
            $record->update([...$fields, 'status' => $target, $timestamp => now()]);
            $this->recordChange($record, $from, $actor, $input['admin_note'] ?? null);

            return $record;
        }, 3);
    }

    /** Transfer a received and inspected out-of-stock claim into the existing refund workflow, once. */
    public function convertToRefund(int $id, User $actor): ReturnRequest
    {
        return DB::connection('tenant')->transaction(function () use ($id, $actor): ReturnRequest {
            $orderId = ReplacementRequest::findOrFail($id)->order_id;
            Order::lockForUpdate()->findOrFail($orderId);
            $record = ReplacementRequest::lockForUpdate()->findOrFail($id);
            if ($record->return_request_id) {
                return $record->returnRequest;
            }
            if ($record->status !== 'out_of_stock' || ! $record->qc_completed_at || $record->stock_reserved) {
                throw ValidationException::withMessages(['status' => 'Only an inspected out-of-stock replacement can be converted to a refund.']);
            }
            $calculation = $this->calculator->calculate($record->item, $record->quantity);
            $return = ReturnRequest::create([
                'return_number' => 'RET-' . now()->format('Ym') . '-' . Str::ulid(),
                'order_id' => $record->order_id,
                'order_item_id' => $record->order_item_id,
                'customer_id' => $record->customer_id,
                'quantity' => $record->quantity,
                'reason_id' => $record->reason_id,
                'reason_note' => $record->reason_note,
                'status' => 'inspection_passed',
                'requested_at' => $record->requested_at,
                'approved_at' => $record->approved_at,
                'received_at' => $record->received_at,
                'inspected_at' => $record->qc_completed_at,
                'inspected_by' => $actor->id,
                'inventory_disposition' => $record->inventory_disposition,
                'refund_amount' => $calculation['total_amount'],
                'calculation' => $calculation,
                'admin_note' => 'Converted from replacement ' . $record->replacement_number,
            ]);
            $record->update(['status' => 'converted_to_refund', 'return_request_id' => $return->id, 'converted_at' => now()]);
            $this->audit->recordSnapshot($return, 'replacement_converted', null, ['replacement_request_id' => $record->id, 'status' => $return->status]);
            $this->recordChange($record, 'out_of_stock', $actor);

            return $return;
        }, 3);
    }

    private function variant(ReplacementRequest $record): ?ProductVariant
    {
        return ProductVariant::withTrashed()->where('product_id', $record->item->product_id)->lockForUpdate()->find($record->item->product_variant_id);
    }

    private function stock(ReplacementRequest $record, ProductVariant $variant, string $event, int $quantity, int $reserved): void
    {
        $this->orders->stock($record->order, $variant, $event, $quantity, $reserved, $record->id);
        $this->audit->recordSnapshot($record, 'inventory_' . $event, null, ['product_variant_id' => $variant->id, 'quantity_delta' => $quantity, 'reserved_delta' => $reserved]);
    }

    private function recordChange(ReplacementRequest $record, ?string $from, User|Customer $actor, ?string $note = null): void
    {
        $record->histories()->create(['from_status' => $from, 'to_status' => $record->status, 'changed_by' => $actor instanceof User ? $actor->id : null, 'customer_id' => $actor instanceof Customer ? $actor->id : null, 'note' => $note, 'created_at' => now()]);
        $this->audit->recordSnapshot($record, $record->status, $from ? ['status' => $from] : null, ['status' => $record->status, 'actor_id' => $actor->id, 'actor_type' => $actor::class]);
        $email = $record->customer?->email;
        $mail = new ReplacementStatusChanged($record->replacement_number, $record->status);
        if ($email) {
            DB::connection('tenant')->afterCommit(function () use ($email, $mail): void {
                try {
                    Mail::to($email)->send($mail);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });
        }
    }
}
