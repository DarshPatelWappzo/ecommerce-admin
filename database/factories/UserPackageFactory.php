<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserPackage>
 */
class UserPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'package_id' => Package::factory(),
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'status' => 'active',
        ];
    }
}
