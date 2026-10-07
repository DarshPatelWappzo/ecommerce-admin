<?php

namespace App\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Repositories\TenantProductRepository;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TenantProductService
{
    public function __construct(private readonly TenantProductRepository $products, private readonly TenantAuditLogService $audit) {}

    /**
     * Persist a product and its related variants, attributes, and images in a tenant transaction.
     *
     * @param  array  $data  The validated product payload including nested variants and images.
     * @param  int|null  $id  The product identifier when updating an existing record.
     * @return Product The saved product instance with refreshed relationships.
     */
    public function save(array $data, ?int $id = null): Product
    {
        $uploaded = [];
        $obsolete = [];
        try {
            $product = DB::connection('tenant')->transaction(function () use ($data, $id, &$uploaded, &$obsolete): Product {
                $product = $id ? $this->products->find($id, true) : new Product;
                $before = $id ? $this->snapshot($product) : null;
                $stockBefore = $id ? $product->variants->keyBy('id')->map->only(['quantity', 'reserved_quantity'])->all() : [];
                $fields = Arr::only($data, ['name', 'slug', 'product_type', 'short_description', 'description', 'status', 'featured', 'meta_title', 'meta_description', 'tax_id', 'hsn_code']);
                foreach (['is_returnable' => 'return_days', 'is_replaceable' => 'replacement_days'] as $flag => $days) {
                    $fields[$flag] = (bool) ($data[$flag] ?? $product->$flag ?? false);
                    $fields[$days] = $fields[$flag] ? ($data[$days] ?? $product->$days) : null;
                }
                if (empty($fields['slug'])) {
                    $fields['slug'] = $product->slug ?: $this->products->uniqueSlug($fields['name']);
                }
                $this->products->saveProduct($product, $fields);
                $variants = $this->products->syncVariants($product, $data['variants']);
                $this->products->syncRelationships($product, $data);
                if (array_key_exists('images', $data)) {
                    $kept = [];
                    foreach ($data['images'] as $row) {
                        $image = ! empty($row['id']) ? $product->images()->findOrFail($row['id']) : $product->images()->make();
                        $file = $row['file'] ?? null;
                        if ($file) {
                            $path = $file->store('products/'.hash('sha256', (string) config('database.connections.tenant.database')).'/'.$product->id, 'public');
                            if (! $path) {
                                throw new \RuntimeException('The product image could not be stored.');
                            }
                            $uploaded[] = $path;
                            if ($image->exists) {
                                $obsolete[] = $image->image;
                            }
                            $image->image = $path;
                        }
                        $image->fill(Arr::only($row, ['alt_text', 'is_primary', 'sort_order']));
                        $image->variant_id = isset($row['variant_index']) ? $variants[$row['variant_index']]->id : null;
                        $image->save();
                        $kept[] = $image->id;
                    }
                    $removed = $product->images()->whereNotIn('id', $kept)->get();
                    foreach ($removed as $image) {
                        $obsolete[] = $image->image;
                        $image->delete();
                    }
                    if (! $product->images()->where('is_primary', true)->exists()) {
                        $product->images()->first()?->update(['is_primary' => true]);
                    }
                }
                $product = $this->products->find($product->id);
                $this->audit->recordSnapshot($product, $id ? TenantAuditAction::UPDATED : TenantAuditAction::CREATED, $before, $this->snapshot($product));
                foreach ($product->variants as $variant) {
                    $previous = $stockBefore[$variant->id] ?? null;
                    $current = $variant->only(['quantity', 'reserved_quantity']);
                    if ($previous !== null && $previous !== $current) {
                        $this->audit->log('inventory', TenantAuditAction::INVENTORY_ADJUSTED, $variant, $previous, $current, metadata: [
                            'quantity_before' => $previous['quantity'],
                            'quantity_after' => $variant->quantity,
                            'difference' => $variant->quantity - $previous['quantity'],
                            'reason' => 'Product inventory adjustment',
                            'product_id' => $product->id,
                        ]);
                    }
                }

                return $product;
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($uploaded);
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['slug' => 'The slug, SKU, or variant combination is already in use. Reload and try again.']);
            }
            throw $exception;
        }
        $this->deleteFiles($obsolete);

        return $product;
    }

    /**
     * Remove a product after validating that it has no reserved stock.
     *
     * @param  int  $id  The product identifier to delete.
     */
    public function delete(int $id): void
    {
        DB::connection('tenant')->transaction(function () use ($id): void {
            $product = $this->products->find($id, true);
            if ($product->variants()->orderBy('id')->lockForUpdate()->get()->contains(fn ($variant) => $variant->reserved_quantity > 0)) {
                throw ValidationException::withMessages(['product' => 'Products with reserved stock cannot be deleted.']);
            }
            $before = $this->snapshot($product);
            $product->variants()->update(['combination_key' => null]);
            $product->variants()->delete();
            $product->delete();
            $this->audit->recordSnapshot($product, 'deleted', $before, null);
        });
    }

    /** Capture business fields without regenerated relation row IDs or duplicate stock values.
     * @return array<string, mixed>
     */
    private function snapshot(Product $product): array
    {
        return [
            ...$product->attributesToArray(),
            'categories' => $product->categories->pluck('id')->sort()->values()->all(),
            'tags' => $product->tags->pluck('id')->sort()->values()->all(),
            'related_products' => $product->relatedProducts->pluck('id')->sort()->values()->all(),
            'attributes' => $product->attributeValues->map->only(['attribute_id', 'attribute_option_id', 'value'])->sortBy('attribute_id')->values()->all(),
            'variants' => $product->variants->sortBy('id')->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => [
                ...$variant->only(['sku', 'price', 'special_price', 'special_price_from', 'special_price_to', 'barcode', 'cost_price', 'weight', 'status', 'reorder_level']),
                'options' => $variant->attributeValues->map->only(['attribute_id', 'attribute_option_id'])->sortBy('attribute_id')->values()->all(),
            ]])->all(),
            'images' => $product->images->map->only(['id', 'image', 'variant_id', 'alt_text', 'is_primary', 'sort_order'])->all(),
        ];
    }

    /**
     * Delete uploaded product image files when a transaction fails during a save operation.
     *
     * @param  array  $paths  The storage paths queued for cleanup.
     */
    private function deleteFiles(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (! Storage::disk('public')->delete($path)) {
                    Log::warning('Product image cleanup failed.', ['path' => $path]);
                }
            } catch (Throwable $exception) {
                Log::warning('Product image cleanup failed.', ['path' => $path, 'exception' => $exception]);
            }
        }
    }
}
