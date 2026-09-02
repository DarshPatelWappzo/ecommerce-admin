<?php

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires all package fields when creating a package', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->post(route('super-admin.package.store'), [])
        ->assertSessionHasErrors([
            'name',
            'slug',
            'min_monthly_users',
            'application_servers',
            'infrastructure_summary',
            'min_monthly_cost',
            'max_monthly_cost',
            'currency',
            'billing_period',
            'sort_order',
            'status',
        ]);
});

it('creates a package from the add package form', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->post(route('super-admin.package.store'), [
            'name' => 'Premium plan',
            'slug' => 'premium-plan',
            'min_monthly_users' => 100,
            'max_monthly_users' => 1000,
            'application_servers' => 2,
            'infrastructure_summary' => 'Managed application infrastructure.',
            'min_monthly_cost' => '49.99',
            'max_monthly_cost' => '199.99',
            'currency' => 'INR',
            'billing_period' => 'monthly',
            'sort_order' => 1,
            'description' => 'A premium package.',
            'status' => 'active',
        ])
        ->assertRedirect(route('super-admin.package.index'));

    $package = Package::query()->where('name', 'Premium plan')->firstOrFail();

    expect($package->description)->toBe('A premium package.')
        ->and((float) $package->min_monthly_cost)->toBe(49.99)
        ->and($package->status)->toBe('active');
});

it('updates a package from the edit package form', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $package = Package::factory()->create();

    $this->actingAs($admin)
        ->put(route('super-admin.package.update', $package), [
            'name' => 'Updated plan',
            'slug' => 'updated-plan',
            'min_monthly_users' => 200,
            'max_monthly_users' => 2000,
            'application_servers' => 3,
            'infrastructure_summary' => 'Updated infrastructure.',
            'min_monthly_cost' => '79.99',
            'max_monthly_cost' => '299.99',
            'currency' => 'INR',
            'billing_period' => 'monthly',
            'sort_order' => 2,
            'description' => 'Updated description.',
            'status' => 'inactive',
        ])
        ->assertRedirect(route('super-admin.package.index'));

    expect($package->refresh()->name)->toBe('Updated plan')
        ->and($package->description)->toBe('Updated description.')
        ->and((float) $package->min_monthly_cost)->toBe(79.99)
        ->and($package->status)->toBe('inactive');
});

it('filters packages by the search query', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    Package::factory()->create(['name' => 'Premium plan']);
    Package::factory()->create(['name' => 'Starter plan']);

    $this->actingAs($admin)
        ->get(route('super-admin.package.index', ['search' => 'Premium']))
        ->assertSee('Premium plan')
        ->assertDontSee('Starter plan');
});
