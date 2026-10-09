<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Cart;
use App\Models\Tenant\CartOrder;
use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartOrder> */
class CartOrderFactory extends Factory
{
    protected $model = CartOrder::class;

    public function definition(): array
    {
        return ['cart_id' => Cart::factory(), 'order_id' => Order::factory(), 'items_snapshot' => [], 'payment_method' => 'cod'];
    }
}
