<?php

namespace App\Http\Resources;

use App\Services\TenantOrderCalculationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $calculator = app(TenantOrderCalculationService::class);
        $available = ! $this->trashed() && $this->status && $this->tax?->is_available;

        return [
            ...$this->resource->only(['id', 'name', 'slug', 'product_type', 'short_description', 'description', 'is_returnable', 'return_days', 'is_replaceable', 'replacement_days']),
            'available' => (bool) $available,
            'images' => $this->images->map(fn($image): array => $image->only(['id', 'url', 'alt_text', 'variant_id'])),
            'tax' => $this->tax?->only(['name', 'code', 'rate']),
            'categories' => $this->categories->where('status', true)->map(fn($category): array => $category->only(['id', 'name', 'slug'])),
            'variants' => $this->variants->where('status', true)->values()->map(fn($variant): array => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $calculator->catalogPrice($variant),
                'available_stock' => $available ? max(0, $variant->quantity - $variant->reserved_quantity) : 0,
                'name' => $variant->attributeValues->map(fn($value) => $value->option?->label)->filter()->implode(' / '),
            ]),
        ];
    }
}
