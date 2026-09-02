<?php

use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
