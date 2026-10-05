<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return ['customer_code' => 'CUS-' . Str::ulid(), 'first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->unique()->safeEmail(), 'customer_type' => 'individual', 'status' => 'active'];
    }
}
