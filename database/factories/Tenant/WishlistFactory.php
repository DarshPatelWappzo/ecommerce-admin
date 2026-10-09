<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Wishlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Wishlist> */
class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'product_id' => Product::factory()];
    }
}
