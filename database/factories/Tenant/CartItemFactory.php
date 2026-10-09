<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Cart;
use App\Models\Tenant\CartItem;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return ['cart_id' => Cart::factory(), 'product_id' => fn(): int => $this->variant()->product_id, 'product_variant_id' => fn(array $attributes): int => ProductVariant::where('product_id', $attributes['product_id'])->firstOrFail()->id, 'quantity' => 1, 'revision' => 1];
    }

    private function variant(): ProductVariant
    {
        return Product::factory()->create()->variants()->create(['sku' => fake()->unique()->uuid(), 'price' => '100.00', 'quantity' => 10]);
    }
}
