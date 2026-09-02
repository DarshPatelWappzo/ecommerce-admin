<?php

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\User;
use App\Models\UserDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records package creation with the authenticated user', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin);
    $package = Package::factory()->create(['name' => 'Gold plan']);

    $auditLog = AuditLog::query()->where('action', 'created')->latest('id')->firstOrFail();

    expect($auditLog->user_id)->toBe($admin->id)
        ->and($auditLog->module)->toBe('packages')
        ->and($auditLog->auditable_type)->toBe(Package::class)
        ->and($auditLog->auditable_id)->toBe($package->id)
        ->and($auditLog->old_values)->toBeNull()
        ->and($auditLog->new_values['name'])->toBe('Gold plan')
        ->and($auditLog->new_values)->not->toHaveKey('created_at')
        ->and($auditLog->description)->toBe('Package created');
});

it('records user creation with the authenticated admin', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin);
    $user = User::factory()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'name' => 'Ada Lovelace',
    ]);

    $auditLog = AuditLog::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'created')
        ->firstOrFail();

    expect($auditLog->user_id)->toBe($admin->id)
        ->and($auditLog->module)->toBe('users')
        ->and($auditLog->new_values['email'])->toBe($user->email)
        ->and($auditLog->new_values)->not->toHaveKey('password');
});

it('records only changed user fields on update', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['first_name' => 'Ada']);

    $this->actingAs($admin);
    $user->update(['first_name' => 'Grace']);

    $auditLog = AuditLog::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'updated')
        ->firstOrFail();

    expect($auditLog->old_values)->toBe(['first_name' => 'Ada'])
        ->and($auditLog->new_values)->toBe(['first_name' => 'Grace']);
});

it('records domain creation, updates, and deletion', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create();

    $this->actingAs($admin);
    $domain = UserDomain::factory()->create([
        'user_id' => $user->id,
        'domain_name' => 'old.example.com',
    ]);

    $createdLog = AuditLog::query()
        ->where('auditable_type', UserDomain::class)
        ->where('auditable_id', $domain->id)
        ->where('action', 'created')
        ->firstOrFail();

    $domain->update(['domain_name' => 'new.example.com']);
    $updatedLog = AuditLog::query()
        ->where('auditable_type', UserDomain::class)
        ->where('auditable_id', $domain->id)
        ->where('action', 'updated')
        ->firstOrFail();

    $domain->delete();
    $deletedLog = AuditLog::query()
        ->where('auditable_type', UserDomain::class)
        ->where('auditable_id', $domain->id)
        ->where('action', 'deleted')
        ->firstOrFail();

    expect($createdLog->module)->toBe('users')
        ->and($createdLog->new_values['domain'])->toBe('old.example.com')
        ->and($updatedLog->old_values)->toBe(['domain' => 'old.example.com'])
        ->and($updatedLog->new_values)->toBe(['domain' => 'new.example.com'])
        ->and($deletedLog->old_values['domain'])->toBe('new.example.com')
        ->and($deletedLog->new_values)->toBeNull();
});

it('records only status when a user status is updated from the admin form', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'name' => 'John Doe',
        'mobile_number' => '1234567890',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->put(route('super-admin.admin.update', $user), [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'mobile_number' => '1234567890',
            'domains' => ['example.com'],
            'status' => 'inactive',
        ])
        ->assertRedirect(route('super-admin.admin.index'));

    $auditLog = AuditLog::query()
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'updated')
        ->firstOrFail();

    expect($auditLog->old_values)->toBe(['status' => 'active'])
        ->and($auditLog->new_values)->toBe(['status' => 'inactive']);
});

it('records only changed package fields on update', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $package = Package::factory()->create(['name' => 'Gold plan']);

    $this->actingAs($admin);
    $package->update(['name' => 'Platinum plan']);

    $auditLog = AuditLog::query()->where('action', 'updated')->latest('id')->firstOrFail();

    expect($auditLog->old_values)->toBe(['name' => 'Gold plan'])
        ->and($auditLog->new_values)->toBe(['name' => 'Platinum plan']);
});

it('does not record an update when no package fields change', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $package = Package::factory()->create();

    $this->actingAs($admin);
    $package->update(['name' => $package->name]);

    expect(AuditLog::query()->where('action', 'updated')->count())->toBe(0);
});

it('keeps deleted package values in the audit log', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $package = Package::factory()->create(['name' => 'Gold plan']);

    $this->actingAs($admin);
    $package->delete();

    $auditLog = AuditLog::query()->where('action', 'deleted')->latest('id')->firstOrFail();

    expect($auditLog->new_values)->toBeNull()
        ->and($auditLog->old_values['name'])->toBe('Gold plan')
        ->and(Package::withTrashed()->find($package->id))->not->toBeNull();
});

it('restricts audit logs to super admins', function () {
    $user = User::factory()->create(['is_super_admin' => false]);

    $this->actingAs($user)
        ->get(route('super-admin.audit-logs.index'))
        ->assertForbidden();
});

it('shows audit logs with filters to super admins', function () {
    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->actingAs($admin);
    Package::factory()->create([
        'name' => 'Gold plan',
        'backup_included' => true,
        'status' => 'inactive',
    ]);

    $this->actingAs($admin)
        ->get(route('super-admin.audit-logs.index', [
            'action' => 'created',
            'module' => 'packages',
            'user_id' => $admin->id,
        ]))
        ->assertOk()
        ->assertSee('Gold plan')
        ->assertSee('Package:')
        ->assertSee('Yes')
        ->assertSee('inactive')
        ->assertSee('fa-eye');
});
