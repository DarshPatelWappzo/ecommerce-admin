<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantCustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->only(['id', 'customer_id', 'label', 'recipient_name', 'phone_country_code', 'phone', 'address_line_1', 'address_line_2', 'landmark', 'city', 'state_code', 'country_code', 'postal_code', 'is_default_shipping', 'is_default_billing', 'created_at', 'updated_at']);
    }
}
