<?php

namespace App\Http\Resources;

use App\Models\Tenant\User;
use App\Repositories\TenantRoleRepository;
use App\Services\TenantOrderCalculationService;
use App\Services\TenantReturnEligibilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->only(['id', 'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'company_name', 'gstin', 'order_date', 'source', 'status', 'payment_status', 'currency', 'coupon_id', 'coupon_code', 'coupon_discount', 'coupon_snapshot', 'subtotal', 'discount_total', 'shipping_amount', 'shipping_tax_amount', 'shipping_tax_rate', 'shipping_tax_name', 'shipping_tax_code', 'tax_total', 'rounding_adjustment', 'grand_total', 'customer_note', 'internal_note', 'created_by', 'cancelled_at', 'cancellation_reason', 'created_at', 'updated_at']);
        $data['dispatch_approved_at'] = $this->dispatch_approved_at;
        $data['dispatch_approved_by'] = $this->dispatch_approved_by;
        $data['invoice_error'] = $this->invoice_error;
        foreach (['items', 'addresses', 'payments', 'shipment', 'histories'] as $relation) {
            $data[$relation] = $this->whenLoaded($relation);
        }
        if ($this->resource->relationLoaded('items')) {
            $data['items'] = $this->items->map(function ($item): array {
                $item->setRelation('order', $this->resource);

                return [...$item->attributesToArray(), 'return' => app(TenantReturnEligibilityService::class)->check($item)];
            });
        }
        if ($this->resource->relationLoaded('returns')) {
            $returned = $this->returns->whereIn('status', ['inspection_passed', 'refund_pending', 'refund_processing', 'refund_failed', 'refunded'])->sum('quantity');
            $returned += $this->returns->where('status', 'closed')->where('inventory_disposition', '!=', 'inspection_failed')->sum('quantity');
            $data['return_status'] = $returned === 0 ? 'none' : ($returned >= $this->items->sum('quantity') ? 'returned' : 'partially_returned');
            $refunded = TenantOrderCalculationService::money('0');
            foreach ($this->returns as $return) {
                if ($return->refund?->status === 'processed') {
                    $refunded = $refunded->plus($return->refund->amount);
                }
            }
            $data['refunded_amount'] = (string) $refunded;
            $data['refund_status'] = $refunded->isZero() ? 'none' : ($refunded->isEqualTo($this->grand_total) ? 'refunded' : 'partially_refunded');
        }
        $actor = $request->user($request->is('api/*') ? 'sanctum' : 'tenant');
        if ($actor instanceof User && app(TenantRoleRepository::class)->userHasPermission($actor, 'invoices.view')) {
            $data['invoice'] = $this->whenLoaded('invoice');
        }
        if ($this->resource->relationLoaded('paymentCheckout')) {
            $data['payment_review_required'] = $this->paymentCheckout?->status === 'review';
        }
        if ($this->resource->relationLoaded('payments')) {
            $received = TenantOrderCalculationService::money('0');
            foreach ($this->payments->where('status', 'captured') as $payment) {
                $received = $received->plus($payment->amount);
            }
            $data['received_amount'] = (string) $received;
            $data['outstanding_amount'] = (string) TenantOrderCalculationService::money($this->grand_total)->minus($received);
        }

        return $data;
    }
}
