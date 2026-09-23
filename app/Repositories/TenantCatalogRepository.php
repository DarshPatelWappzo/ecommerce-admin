<?php

namespace App\Repositories;

use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductAttributeValue;
use App\Models\Tenant\Tag;
use App\Models\Tenant\VariantAttributeValue;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TenantCatalogRepository
{
    public function all(): array
    {
        return ['tags' => Tag::orderBy('name')->get(), 'attributes' => ProductAttribute::with('options')->orderBy('name')->get()];
    }

    public function saveTag(array $data): Tag
    {
        $tag = ! empty($data['id']) ? Tag::findOrFail($data['id']) : new Tag;
        $tag->fill(Arr::only($data, ['name', 'slug', 'status']))->save();

        return $tag;
    }

    public function saveAttribute(array $data): ProductAttribute
    {
        $attribute = ! empty($data['id']) ? ProductAttribute::lockForUpdate()->findOrFail($data['id']) : new ProductAttribute;
        if (
            $attribute->exists && (! $data['status'] || ($attribute->is_variant && ! $data['is_variant']))
            && (ProductAttributeValue::where('attribute_id', $attribute->id)->exists() || VariantAttributeValue::where('attribute_id', $attribute->id)->exists())
        ) {
            throw ValidationException::withMessages(['status' => 'An attribute used by products must remain active and keep its variant setting.']);
        }
        if (
            $attribute->exists && $attribute->type !== $data['type']
            && (ProductAttributeValue::where('attribute_id', $attribute->id)->exists() || VariantAttributeValue::where('attribute_id', $attribute->id)->exists())
        ) {
            throw ValidationException::withMessages(['type' => 'The type cannot change while products use this attribute.']);
        }
        $attribute->fill(Arr::only($data, ['name', 'code', 'type', 'is_variant', 'is_filterable', 'status']))->save();
        $keptIds = collect($data['options'])->pluck('id')->filter()->all();
        $removed = $attribute->options()->whereNotIn('id', $keptIds)->pluck('id');
        if (ProductAttributeValue::whereIn('attribute_option_id', $removed)->exists() || VariantAttributeValue::whereIn('attribute_option_id', $removed)->exists()) {
            throw ValidationException::withMessages(['options' => 'Options used by products cannot be removed.']);
        }
        $attribute->options()->whereIn('id', $removed)->delete();
        foreach ($data['options'] as $row) {
            $option = ! empty($row['id']) ? $attribute->options()->findOrFail($row['id']) : $attribute->options()->make();
            $option->fill(Arr::only($row, ['value', 'label', 'sort_order']))->save();
        }

        return $attribute->load('options');
    }
}
