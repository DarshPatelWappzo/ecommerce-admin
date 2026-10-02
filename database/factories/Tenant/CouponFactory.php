<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coupon> */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return ['code' => strtoupper(fake()->unique()->bothify('SAVE-########')), 'discount_type' => 'percentage', 'discount_value' => '10.00', 'minimum_subtotal' => '0.00', 'is_active' => true];
    }
}
