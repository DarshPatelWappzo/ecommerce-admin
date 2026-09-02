<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the super admin login page', function () {
    $this->get('/super-admin/login')
        ->assertSuccessful()
        ->assertSee('Super Admin Login')
        ->assertDontSee('Forgot password');
});

it('allows a seeded super admin to log in and view the dashboard', function () {
    $superAdmin = User::factory()->create([
        'email' => 'superadmin@gmail.com',
        'password' => 'Superadmin@123',
        'is_super_admin' => true,
    ]);

    $this->post('/super-admin/login', [
        'email' => 'superadmin@gmail.com',
        'password' => 'Superadmin@123',
    ])->assertRedirect('/super-admin/dashboard');

    $this->assertAuthenticatedAs($superAdmin);
    $this->get('/super-admin/dashboard')->assertSuccessful()->assertSee('Administration overview');
});

it('rejects non-super-admin users from the dashboard', function () {
    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user)
        ->get('/super-admin/dashboard')
        ->assertForbidden();
});

it('redirects guests to the super admin login page', function () {
    $this->get('/super-admin/dashboard')->assertRedirect('/super-admin/login');
});

it('logs out a super admin and invalidates the session', function () {
    $superAdmin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($superAdmin)
        ->post('/super-admin/logout')
        ->assertRedirect('/super-admin/login');

    $this->assertGuest();
});
