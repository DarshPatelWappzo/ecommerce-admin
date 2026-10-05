<?php

namespace App\Http\Resources;

use App\Models\Tenant\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->only(['id', 'customer_code', 'first_name', 'last_name', 'email', 'phone_country_code', 'phone', 'customer_type', 'company_name', 'gstin', 'status', 'email_verified_at', 'phone_verified_at', 'created_at', 'updated_at']);
        $data['notes'] = $this->when($request->is('api/tenant/*') && $request->user('sanctum') instanceof User, $this->notes);
        $data['addresses'] = TenantCustomerAddressResource::collection($this->whenLoaded('addresses'));

        return $data;
    }
}
