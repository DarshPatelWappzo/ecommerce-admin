<?php

namespace App\Http\Requests;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TenantProductSaveRequest extends TenantProductRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payload'))) {
            $payload = json_decode($this->input('payload'), true);
            if (is_array($payload)) {
                $this->merge($payload);
            }
        }
        $name = $this->input('name');
        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => trim($this->input('slug'))]);
        }
        if ($this->input('tax_id') === '') {
            $this->merge(['tax_id' => null]);
        }
    }

    public function rules(): array
    {
        $productId = $this->route('product');
        $currentTaxId = $productId !== null ? Product::findOrFail($productId)->tax_id : null;
        $rules = [
            'tax_id' => ['nullable', 'integer', Rule::exists('tenant.taxes', 'id')->where(function (Builder $query) use ($currentTaxId): void {
                $query->where(function (Builder $query) use ($currentTaxId): void {
                    $query->where(fn (Builder $available) => $available->where('is_active', true)->whereNull('deleted_at'));
                    if ($currentTaxId !== null) {
                        $query->orWhere('id', $currentTaxId);
                    }
                });
            })],
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tenant.products', 'slug')->ignore($productId)],
            'product_type' => ['required', Rule::in(['simple', 'configurable'])],
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', 'boolean'],
            'featured' => ['sometimes', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
            'category_ids' => ['present', 'array', 'max:100'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('tenant.categories', 'id')->whereNull('deleted_at')],
            'tag_ids' => ['present', 'array', 'max:100'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists('tenant.tags', 'id')],
            'related_product_ids' => ['sometimes', 'array', 'max:100'],
            'related_product_ids.*' => ['integer', 'distinct', Rule::notIn([$productId]), Rule::exists('tenant.products', 'id')->whereNull('deleted_at')],
            'variants' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'variants.*' => ['array:id,sku,barcode,price,special_price,special_price_from,special_price_to,cost_price,weight,quantity,reserved_quantity,reorder_level,status,options'],
            'variants.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('tenant.product_variants', 'id')->where('product_id', $productId ?? 0)->whereNull('deleted_at')],
            'variants.*.sku' => ['required', 'string', 'max:191', 'distinct:ignore_case'],
            'variants.*.barcode' => ['nullable', 'string', 'max:191'],
            'variants.*.price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'variants.*.special_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0', 'max:999999999.999'],
            'variants.*.special_price_from' => ['nullable', 'date_format:Y-m-d'],
            'variants.*.special_price_to' => ['nullable', 'date_format:Y-m-d'],
            'variants.*.quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'variants.*.reserved_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'variants.*.reorder_level' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'variants.*.status' => ['required', 'boolean'],
            'variants.*.options' => ['present', 'array', 'max:20'],
            'variants.*.options.*' => ['array:attribute_id,attribute_option_id'],
            'variants.*.options.*.attribute_id' => ['required', 'integer'],
            'variants.*.options.*.attribute_option_id' => ['required', 'integer'],
            'attributes' => ['sometimes', 'array', 'max:50'],
            'attributes.*' => ['array:attribute_id,attribute_option_id,text_value'],
            'attributes.*.attribute_id' => ['required', 'integer', 'distinct'],
            'attributes.*.attribute_option_id' => ['nullable', 'integer'],
            'attributes.*.text_value' => ['nullable', 'string', 'max:2000'],
            'images' => ['sometimes', 'array', 'max:20'],
            'images.*' => ['array:id,file,variant_index,alt_text,is_primary,sort_order'],
            'images.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('tenant.product_images', 'id')->where('product_id', $productId ?? 0)],
            'images.*.file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120'],
            'images.*.variant_index' => ['nullable', 'integer', 'min:0'],
            'images.*.alt_text' => ['nullable', 'string', 'max:191'],
            'images.*.is_primary' => ['required', 'boolean'],
            'images.*.sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
        ];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $variants = $this->input('variants', []);
            $attributeRows = $this->input('attributes', []);
            $attributeIds = collect($attributeRows)->pluck('attribute_id')
                ->merge(collect($variants)->flatMap(fn ($variant) => collect($variant['options'])->pluck('attribute_id')));
            $definitions = ProductAttribute::with('options')->whereIn('id', $attributeIds)->get()->keyBy('id');
            $existingSkus = ProductVariant::withTrashed()->whereIn('sku', collect($variants)->pluck('sku')->map(fn ($sku) => Str::upper(trim($sku))))->get()->keyBy(fn ($variant) => Str::upper($variant->sku));
            $combinations = [];
            $expectedAttributes = null;
            foreach ($variants as $index => $variant) {
                $prefix = 'variants.'.$index;
                $sku = Str::upper(trim($variant['sku']));
                if ($sku === '' || (isset($existingSkus[$sku]) && $existingSkus[$sku]->id !== (int) ($variant['id'] ?? 0))) {
                    $validator->errors()->add($prefix.'.sku', 'SKU is empty or already in use.');
                }
                if ($variant['reserved_quantity'] > $variant['quantity']) {
                    $validator->errors()->add($prefix.'.reserved_quantity', 'Reserved quantity cannot exceed quantity.');
                }
                if (isset($variant['special_price']) && $variant['special_price'] > $variant['price']) {
                    $validator->errors()->add($prefix.'.special_price', 'Special price cannot exceed the regular price.');
                }
                if (! empty($variant['special_price_from']) && ! empty($variant['special_price_to']) && $variant['special_price_to'] < $variant['special_price_from']) {
                    $validator->errors()->add($prefix.'.special_price_to', 'End date must be on or after the start date.');
                }
                $options = $variant['options'];
                if ($this->input('product_type') === 'simple' && (count($variants) !== 1 || count($options) !== 0)) {
                    $validator->errors()->add('variants', 'Simple products require exactly one variant without variant options.');
                }
                if ($this->input('product_type') === 'configurable' && count($options) === 0) {
                    $validator->errors()->add($prefix.'.options', 'Choose at least one variant attribute option.');
                }
                $pairs = [];
                foreach ($options as $optionIndex => $option) {
                    $definition = $definitions->get($option['attribute_id']);
                    if (
                        ! $definition || ! $definition->status || ! $definition->is_variant || $definition->type !== 'select'
                        || ! $definition->options->contains('id', $option['attribute_option_id']) || isset($pairs[$option['attribute_id']])
                    ) {
                        $validator->errors()->add($prefix.'.options.'.$optionIndex.'.attribute_option_id', 'Choose a valid, distinct variant attribute and one of its options.');
                    }
                    $pairs[(int) $option['attribute_id']] = (int) $option['attribute_option_id'];
                }
                ksort($pairs);
                $key = json_encode($pairs);
                if (in_array($key, $combinations, true)) {
                    $validator->errors()->add($prefix.'.options', 'This variant combination is duplicated.');
                }
                $combinations[] = $key;
                if ($expectedAttributes !== null && $expectedAttributes !== array_keys($pairs)) {
                    $validator->errors()->add($prefix.'.options', 'All variants must use the same attributes.');
                }
                $expectedAttributes = array_keys($pairs);
            }
            foreach ($attributeRows as $index => $row) {
                $definition = $definitions->get($row['attribute_id']);
                $valid = $definition && $definition->status;
                if ($valid && $definition->type === 'select') {
                    $valid = $definition->options->contains('id', $row['attribute_option_id'] ?? null) && empty($row['text_value']);
                } elseif ($valid) {
                    $value = $row['text_value'] ?? null;
                    $valid = empty($row['attribute_option_id']) && $value !== null && $value !== '' && match ($definition->type) {
                        'number' => is_numeric($value),
                        'boolean' => in_array($value, ['0', '1'], true),
                        'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && ($date = \DateTime::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value,
                        default => true,
                    };
                }
                if (! $valid) {
                    $validator->errors()->add('attributes.'.$index.'.text_value', 'The value must match the attribute type and its available options.');
                }
            }
            $primaryCount = 0;
            foreach ($this->input('images', []) as $index => $image) {
                $primaryCount += (int) ($image['is_primary'] ?? 0);
                if (empty($image['id']) && ! $this->hasFile('images.'.$index.'.file')) {
                    $validator->errors()->add('images.'.$index.'.file', 'Choose an image file.');
                }
                if (isset($image['variant_index']) && ! array_key_exists($image['variant_index'], $variants)) {
                    $validator->errors()->add('images.'.$index.'.variant_index', 'Choose a variant belonging to this product.');
                }
            }
            if ($primaryCount > 1) {
                $validator->errors()->add('images', 'Choose only one primary image.');
            }
        }];
    }
}
