<?php

namespace App\Http\Resources;

use App\Services\TenantOrderCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->only(['id', 'order_number', 'customer_id', 'customer_name', 'customer_email', 'customer_phone', 'company_name', 'gstin', 'order_date', 'source', 'status', 'payment_status', 'currency', 'coupon_id', 'coupon_code', 'coupon_discount', 'coupon_snapshot', 'subtotal', 'discount_total', 'shipping_amount', 'shipping_tax_amount', 'shipping_tax_rate', 'shipping_tax_name', 'shipping_tax_code', 'tax_total', 'rounding_adjustment', 'grand_total', 'customer_note', 'internal_note', 'created_by', 'cancelled_at', 'cancellation_reason', 'created_at', 'updated_at']);
        foreach (['items', 'addresses', 'payments', 'shipment', 'histories'] as $relation) {
            $data[$relation] = $this->whenLoaded($relation);
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
