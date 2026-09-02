<?php

use App\Models\User;
use App\Models\UserDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a regular user and multiple domains from the add user form', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->post('/super-admin/admin', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'mobile_number' => '+1 555 0100',
            'domains' => ['example.com', 'shop.example.com'],
            'status' => 'active',
        ])
        ->assertRedirect('/super-admin/admin');

    $user = User::where('email', 'john@example.com')->firstOrFail();

    expect($user->is_super_admin)->toBeFalse()
        ->and($user->domains()->pluck('domain_name')->all())->toBe(['example.com', 'shop.example.com']);
});

it('rejects a duplicate email address', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    User::factory()->create(['email' => 'existing@example.com']);

    $this->actingAs($admin)
        ->post('/super-admin/admin', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'mobile_number' => '+1 555 0100',
            'domains' => ['example.com'],
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');
});

it('rejects a duplicate domain', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    UserDomain::factory()->create(['domain_name' => 'existing.example.com']);

    $this->actingAs($admin)
        ->post('/super-admin/admin', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'mobile_number' => '+1 555 0100',
            'domains' => ['existing.example.com'],
            'status' => 'active',
        ])
        ->assertSessionHasErrors('domains.0');
});

it('returns validation errors as json for an asynchronous submission', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin)
        ->withHeader('Accept', 'application/json')
        ->post('/super-admin/admin')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'mobile_number', 'domains', 'status']);
});

it('filters users by the search query', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com']);
    User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Jones', 'email' => 'bob@example.com']);

    $this->actingAs($admin)
        ->get(route('super-admin.admin.index', ['search' => 'Alice']))
        ->assertSee('alice@example.com')
        ->assertDontSee('bob@example.com');
});

it('shows the edit page with a non-editable email and an index action', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['email' => 'john@example.com']);
    UserDomain::factory()->create(['user_id' => $user->id, 'domain_name' => 'example.com']);

    $this->actingAs($admin)
        ->get('/super-admin/admin')
        ->assertSee(route('super-admin.admin.edit', $user));

    $this->actingAs($admin)
        ->get(route('super-admin.admin.edit', $user))
        ->assertSee('value="john@example.com"', false)
        ->assertSee('readonly', false)
        ->assertSee('Edit user');
});

it('updates a regular user without changing the email address', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['email' => 'john@example.com']);
    $removedDomain = UserDomain::factory()->create(['user_id' => $user->id, 'domain_name' => 'example.com']);
    UserDomain::factory()->create(['user_id' => $user->id, 'domain_name' => 'shop.example.com']);

    $this->actingAs($admin)
        ->put(route('super-admin.admin.update', $user), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'changed@example.com',
            'mobile_number' => '+1 555 0101',
            'domains' => ['shop.example.com', 'new-example.com'],
            'status' => 'inactive',
        ])
        ->assertRedirect('/super-admin/admin');

    expect($user->refresh()->email)->toBe('john@example.com')
        ->and($user->first_name)->toBe('Jane')
        ->and($user->status)->toBe('inactive')
        ->and($user->domains()->pluck('domain_name')->all())->toBe(['shop.example.com', 'new-example.com'])
        ->and(UserDomain::withTrashed()->find($removedDomain->id)->trashed())->toBeTrue();
});
