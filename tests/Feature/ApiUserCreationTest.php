<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires an authenticated super admin', function () {
    $this->postJson('/api/users', [])->assertUnauthorized();

    $this->actingAs(User::factory()->create(['is_super_admin' => false]))
        ->postJson('/api/users', [])
        ->assertForbidden();
});

it('creates a regular user and domains through the api', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'mobile_number' => '9876543210',
        'domains' => ['example.com', 'shop.example.com'],
        'status' => 'active',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'User created successfully.')
        ->assertJsonPath('data.email', 'ada@example.com')
        ->assertJsonPath('data.domains.0', 'example.com');

    $user = User::query()->where('email', 'ada@example.com')->firstOrFail();

    expect($user->is_super_admin)->toBeFalse()
        ->and($user->domains)->toHaveCount(2);
});
