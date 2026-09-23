<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tax> */
class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('TAX-????-####')),
            'rate' => '5.0000',
            'description' => null,
            'is_active' => true,
        ];
    }
}
