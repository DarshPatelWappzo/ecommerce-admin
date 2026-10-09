<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Cart;
use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'active_customer_id' => fn(array $attributes): int => $attributes['customer_id'], 'status' => 'active'];
    }
}
