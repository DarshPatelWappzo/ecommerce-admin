<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.Str::ulid(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'order_date' => now(),
            'source' => 'admin',
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'currency' => 'INR',
            'pricing_fingerprint' => hash('sha256', 'factory'),
        ];
    }
}
