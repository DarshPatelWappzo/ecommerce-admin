<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerAddress> */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return ['customer_id' => Customer::factory(), 'recipient_name' => fake()->name(), 'phone_country_code' => '+91', 'phone' => '9876543210', 'address_line_1' => '12 Market Road', 'city' => 'Ahmedabad', 'state_code' => 'GJ', 'country_code' => 'IN', 'postal_code' => '380001'];
    }
}
