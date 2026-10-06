<?php

namespace App\Repositories;

use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductAttribute;
use App\Models\Tenant\Tag;
use App\Models\Tenant\Tax;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    /**
     * Retrieve a product by identifier, optionally locking the row for an update.
     *
     * @param  int  $id  The product identifier.
     * @param  bool  $lock  Whether to lock the row for concurrent update protection.
     * @return Product The matching product model with eager-loaded relationships.
     */
    public function find(int $id, bool $lock = false): Product
    {
        return Product::with(self::RELATIONS)->when($lock, fn(Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    /**
     * List products with support for search, category/tag filters, and stock-based ordering.
     *
     * @param  array  $filters  The validated filter and pagination payload.
     * @return LengthAwarePaginator The paginated product collection.
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::with(self::RELATIONS)->withMin('variants', 'price');
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn(Builder $query) => $query->where('name', 'like', '%' . $search . '%')
                ->orWhereHas('variants', fn(Builder $query) => $query->where('sku', 'like', '%' . $search . '%')));
        }
        foreach (['category' => 'categories', 'tag' => 'tags'] as $key => $relation) {
            if (! empty($filters[$key])) {
                $query->whereHas($relation, fn(Builder $query) => $query->whereKey($filters[$key]));
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

    /**
     * Build the catalog data used by the product form and related selection lists.
     *
     * @param  Product|null  $product  The active product used to preserve its current selections.
     * @return array The option payload for the form.
     */
    public function options(?Product $product = null): array
    {
        $data = $this->filterOptions();
        $data['taxes'] = Tax::withTrashed()
            ->where(function (Builder $query) use ($product): void {
                $query->where(fn(Builder $available) => $available->where('is_active', true)->whereNull('deleted_at'));
                if ($product?->tax_id !== null) {
                    $query->orWhere('id', $product->tax_id);
                }
            })
            ->orderBy('name')->get(['id', 'name', 'code', 'rate', 'is_active', 'deleted_at']);
        if ($product) {
            $data['tags'] = Tag::where(fn(Builder $query) => $query->where('status', true)->orWhereIn('id', $product->tags->modelKeys()))->orderBy('name')->get(['id', 'name']);
        }
        $data['attributes'] = ProductAttribute::with('options')->where('status', true)->orderBy('name')->get();
        $data['related_products'] = Product::where('id', '!=', $product?->id ?? 0)->orderBy('name')->get(['id', 'name']);

        return $data;
    }

    /**
     * Return the base lookup data for categories and tags that can be used in filters.
     *
     * @return array The base option list for product filtering and forms.
     */
    public function filterOptions(): array
    {
        $data = [];
        $data['categories'] = Category::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $data['tags'] = Tag::where('status', true)->orderBy('name')->get(['id', 'name']);

        return $data;
    }

    /**
     * Generate a unique slug for the product name while avoiding collisions in soft-deleted records.
     *
     * @param  string  $name  The product name used for slug generation.
     * @return string A unique slug value.
     */
    public function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'product', 175, '');
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /**
     * Persist the core product fields for an existing or new product instance.
     *
     * @param  Product  $product  The product model instance.
     * @param  array  $attributes  The product attributes to store.
     * @return Product The saved product model.
     */
    public function saveProduct(Product $product, array $attributes): Product
    {
        $product->fill($attributes)->save();

        return $product;
    }

    /**
     * Synchronize the product variants, including validation for reserved stock and combination keys.
     *
     * @param  Product  $product  The parent product model.
     * @param  array  $rows  The variant rows to save or remove.
     * @return array The saved variant models in submission order.
     */
    public function syncVariants(Product $product, array $rows): array
    {
        $existing = $product->variants()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $keptIds = collect($rows)->pluck('id')->filter()->all();
        $removed = $existing->except($keptIds);
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
            if ($id) {
                $reservedByOrders = (int) DB::connection('tenant')->table('order_stock_movements')->where('product_variant_id', $id)->lockForUpdate()->get()->sum('reserved_delta');
                $reservedByReplacements = (int) DB::connection('tenant')->table('replacement_stock_movements')->where('product_variant_id', $id)->lockForUpdate()->get()->sum('reserved_delta');
                $reservedByOrders += $reservedByReplacements;
                if (($row['reserved_quantity'] ?? 0) < $reservedByOrders || ($row['quantity'] ?? 0) < $reservedByOrders) {
                    throw ValidationException::withMessages(['variants' => 'Stock cannot be reduced below the quantity reserved by orders. Reload the product before editing inventory.']);
                }
                if ($reservedByReplacements > 0 && ($row['sku'] !== $variant->sku || $pairs != $variant->attributeValues->pluck('attribute_option_id', 'attribute_id')->sortKeys()->all())) {
                    throw ValidationException::withMessages(['variants' => 'A SKU or variant reserved for a replacement cannot be changed before dispatch.']);
                }
            }
            $variant->fill($row)->save();
            $variant->attributeValues()->delete();
            $variant->attributeValues()->createMany($options);
            $saved[] = $variant;
        }

        return $saved;
    }

    /**
     * Synchronize the product relationship records for categories, tags, linked products, and attributes.
     *
     * @param  Product  $product  The product whose relationships should be synced.
     * @param  array  $data  The request payload containing relationship IDs and attribute rows.
     */
    public function syncRelationships(Product $product, array $data): void
    {
        $product->categories()->sync($data['category_ids']);
        $product->tags()->sync($data['tag_ids']);
        $product->relatedProducts()->sync($data['related_product_ids'] ?? []);
        $product->attributeValues()->delete();
        $product->attributeValues()->createMany($data['attributes'] ?? []);
    }
}
