<?php

use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\TenantProvisioningController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantCategoryController;
use App\Http\Controllers\TenantRoleController;
use App\Http\Controllers\TenantUserController;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\RequireTenantPasswordChange;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('tenant')->name('tenant.')->group(function (): void {
    Route::get('/login', [TenantAuthController::class, 'showLogin'])->name('login.form');
    Route::post('/login', [TenantAuthController::class, 'login'])
        ->middleware(ResolveTenant::class)
        ->name('login.store');

    Route::middleware([ResolveTenant::class, 'auth:tenant'])->group(function (): void {
        Route::get('/change-password', [TenantAuthController::class, 'passwordForm'])->name('password.form');
        Route::post('/change-password', [TenantAuthController::class, 'changePassword'])->name('password.change');
        Route::post('/logout', [TenantAuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', [TenantAuthController::class, 'dashboard'])
            ->middleware(RequireTenantPasswordChange::class)
            ->name('dashboard');

        Route::middleware(RequireTenantPasswordChange::class)->group(function (): void {
            Route::resource('categories', TenantCategoryController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('categories');
            Route::resource('roles', TenantRoleController::class)->except(['show'])->names('roles');
            Route::resource('users', TenantUserController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->names('users');
        });
    });
});

Route::prefix('super-admin')->name('super-admin.')->group(function (): void {
    Route::controller(AuthController::class)->group(function (): void {
        Route::get('/login', 'create')->name('login');
        Route::post('/login', 'store')->name('login.store');
        Route::post('/logout', 'destroy')
            ->middleware(['auth', EnsureSuperAdmin::class])
            ->name('logout');
    });

    Route::view('/dashboard', 'super-admin.dashboard')
        ->middleware(['auth', EnsureSuperAdmin::class])
        ->name('dashboard');

    Route::middleware(['auth', EnsureSuperAdmin::class])->group(function (): void {
        Route::controller(UserController::class)->group(function (): void {
            Route::get('/admin', 'index')->name('admin.index');
            Route::get('/admin/create', 'create')->name('admin.create');
            Route::post('/admin', 'store')->name('admin.store');
            Route::get('/admin/{user}/edit', 'edit')->name('admin.edit');
            Route::put('/admin/{user}', 'update')->name('admin.update');
        });

        Route::post('/admin/{user}/tenant-databases/{tenantDatabase}/retry', TenantProvisioningController::class)
            ->scopeBindings()
            ->name('admin.tenant-databases.retry');

        Route::controller(PackageController::class)->group(function (): void {
            Route::get('/package', 'index')->name('package.index');
            Route::get('/package/create', 'create')->name('package.create');
            Route::post('/package', 'store')->name('package.store');
            Route::get('/package/{package}/edit', 'edit')->name('package.edit');
            Route::put('/package/{package}', 'update')->name('package.update');
        });

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
