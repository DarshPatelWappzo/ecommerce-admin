<?php

use App\Models\Package;
use App\Models\User;
use App\Models\UserDomain;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('creates the required tables and columns', function () {
    expect(Schema::hasColumns('users', ['first_name', 'last_name', 'mobile_number', 'status', 'deleted_at']))->toBeTrue()
        ->and(Schema::hasColumns('packages', [
            'name',
            'slug',
            'min_monthly_users',
            'max_monthly_users',
            'cpu_vcores',
            'ram_gb',
            'application_servers',
            'database_type',
            'infrastructure_summary',
            'min_monthly_cost',
            'max_monthly_cost',
            'currency',
            'billing_period',
            'bandwidth_gb',
            'storage_gb',
            'backup_included',
            'cdn_included',
            'load_balancer_included',
            'description',
            'cost_disclaimer',
            'sort_order',
            'is_recommended',
            'status',
            'deleted_at',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('user_packages', ['user_id', 'package_id', 'start_date', 'end_date', 'status', 'deleted_at']))->toBeTrue()
        ->and(Schema::hasColumns('user_domains', ['user_id', 'domain_name', 'deleted_at']))->toBeTrue();
});

it('connects users, packages, subscriptions, and domains through relationships', function () {
    $user = User::factory()->create();
    $package = Package::factory()->create();
    $userPackage = UserPackage::factory()->create(['user_id' => $user->id, 'package_id' => $package->id]);
    $userDomain = UserDomain::factory()->create(['user_id' => $user->id]);

    expect($user->userPackages->first()->is($userPackage))->toBeTrue()
        ->and($user->domains->first()->is($userDomain))->toBeTrue()
        ->and($userPackage->user->is($user))->toBeTrue()
        ->and($userPackage->package->is($package))->toBeTrue()
        ->and($package->userPackages->first()->is($userPackage))->toBeTrue();
});

it('soft deletes records without removing them permanently', function () {
    $package = Package::factory()->create();

    $package->delete();

    expect(Package::find($package->id))->toBeNull()
        ->and(Package::withTrashed()->find($package->id)->trashed())->toBeTrue();
});
