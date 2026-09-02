<?php

namespace Database\Factories;

use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Package>
 */
class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'min_monthly_users' => 0,
            'max_monthly_users' => fake()->numberBetween(100, 10000),
            'cpu_vcores' => fake()->randomFloat(2, 1, 32),
            'ram_gb' => fake()->randomFloat(2, 1, 128),
            'application_servers' => fake()->numberBetween(1, 5),
            'database_type' => 'MySQL',
            'infrastructure_summary' => fake()->sentence(),
            'min_monthly_cost' => fake()->randomFloat(2, 10, 500),
            'max_monthly_cost' => fake()->randomFloat(2, 500, 5000),
            'currency' => 'INR',
            'billing_period' => 'monthly',
            'bandwidth_gb' => fake()->randomFloat(2, 10, 1000),
            'storage_gb' => fake()->randomFloat(2, 10, 1000),
            'backup_included' => false,
            'cdn_included' => false,
            'load_balancer_included' => false,
            'description' => fake()->optional()->sentence(),
            'cost_disclaimer' => fake()->optional()->sentence(),
            'sort_order' => 0,
            'is_recommended' => false,
            'status' => 'active',
        ];
    }
}
