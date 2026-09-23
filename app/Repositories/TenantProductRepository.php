<?php

namespace App\Repositories;

use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\Tag;
use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantProductRepository
{
    public const RELATIONS = [
        'tax:id,name,code,rate,is_active,deleted_at',
        'categories:id,name',
        'tags:id,name',
        'variants.attributeValues.attribute',
        'variants.attributeValues.option',
        'attributeValues.attribute',
        'attributeValues.option',
        'images',
        'relatedProducts:id,name',
    ];

    public function find(int $id, bool $lock = false): Product
    {
        return Product::with(self::RELATIONS)->when($lock, fn (Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::with(self::RELATIONS)->withMin('variants', 'price');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')
                ->orWhereHas('variants', fn (Builder $query) => $query->where('sku', 'like', '%'.$search.'%')));
        }
        foreach (['category' => 'categories', 'tag' => 'tags'] as $key => $relation) {
            if (! empty($filters[$key])) {
                $query->whereHas($relation, fn (Builder $query) => $query->whereKey($filters[$key]));
            }
        }
        foreach (['status', 'product_type'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['min_price']) || isset($filters['max_price']) || ! empty($filters['stock'])) {
            $query->whereHas('variants', function (Builder $query) use ($filters): void {
                if (isset($filters['min_price'])) {
                    $query->where('price', '>=', $filters['min_price']);
                }
                if (isset($filters['max_price'])) {
                    $query->where('price', '<=', $filters['max_price']);
                }
                match ($filters['stock'] ?? '') {
                    'in_stock' => $query->whereColumn('quantity', '>', 'reserved_quantity'),
                    'out_of_stock' => $query->whereColumn('quantity', '<=', 'reserved_quantity'),
                    'low_stock' => $query->whereRaw('quantity - reserved_quantity <= reorder_level'),
                    default => null,
                };
            });
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            'price_asc' => $query->orderBy('variants_min_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('variants_min_price')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return $query->paginate($filters['per_page'] ?? 10)->withQueryString();
    }

    public function options(?Product $product = null): array
    {
        $data = $this->filterOptions();
        $data['taxes'] = Tax::withTrashed()
            ->where(function (Builder $query) use ($product): void {
                $query->where(fn (Builder $available) => $available->where('is_active', true)->whereNull('deleted_at'));
                if ($product?->tax_id !== null) {
                    $query->orWhere('id', $product->tax_id);
                }
            })
            ->orderBy('name')->get(['id', 'name', 'code', 'rate', 'is_active', 'deleted_at']);
        if ($product) {
            $data['tags'] = Tag::where(fn (Builder $query) => $query->where('status', true)->orWhereIn('id', $product->tags->modelKeys()))->orderBy('name')->get(['id', 'name']);
        }
        $data['attributes'] = ProductAttribute::with('options')->where('status', true)->orderBy('name')->get();
        $data['related_products'] = Product::where('id', '!=', $product?->id ?? 0)->orderBy('name')->get(['id', 'name']);

        return $data;
    }

    public function filterOptions(): array
    {
        $data = [];
        $data['categories'] = Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $data['tags'] = Tag::where('status', true)->orderBy('name')->get(['id', 'name']);

        return $data;
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'product', 175, '');
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function saveProduct(Product $product, array $attributes): Product
    {
        $product->fill($attributes)->save();

        return $product;
    }

    public function syncVariants(Product $product, array $rows): array
    {
        $keptIds = collect($rows)->pluck('id')->filter()->all();
        $removed = $product->variants()->whereNotIn('id', $keptIds)->get();
        foreach ($removed as $variant) {
            if ($variant->reserved_quantity > 0) {
                throw ValidationException::withMessages(['variants' => 'A variant with reserved stock cannot be removed.']);
            }
            $variant->update(['combination_key' => null]);
            $product->images()->where('variant_id', $variant->id)->update(['variant_id' => null]);
            $variant->delete();
        }
        $product->variants()->update(['combination_key' => null]);
        $saved = [];
        foreach ($rows as $row) {
            $options = $row['options'];
            $id = $row['id'] ?? null;
            unset($row['options'], $row['id']);
            $pairs = collect($options)->pluck('attribute_option_id', 'attribute_id')->sortKeys()->all();
            $row['combination_key'] = hash('sha256', json_encode($pairs));
            $row['sku'] = Str::upper(trim($row['sku']));
            $variant = $id ? $product->variants()->findOrFail($id) : $product->variants()->make();
            $variant->fill($row)->save();
            $variant->attributeValues()->delete();
            $variant->attributeValues()->createMany($options);
            $saved[] = $variant;
        }

        return $saved;
    }

    public function syncRelationships(Product $product, array $data): void
    {
        $product->categories()->sync($data['category_ids']);
        $product->tags()->sync($data['tag_ids']);
        $product->relatedProducts()->sync($data['related_product_ids'] ?? []);
        $product->attributeValues()->delete();
        $product->attributeValues()->createMany($data['attributes'] ?? []);
    }
}
