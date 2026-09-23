<?php

use App\Http\Controllers\Api\Tenant\TenantAuthController;
use App\Http\Controllers\Api\Tenant\TenantCategoryController;
use App\Http\Controllers\Api\Tenant\TenantProductController;
use App\Http\Controllers\Api\Tenant\TenantUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\TenantCatalogController;
use App\Http\Middleware\AuthenticateTenantToken;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Support\Facades\Route;

Route::controller(UserController::class)->group(function (): void {
    Route::get('/users', 'index')->name('api.users.index');
    Route::post('/users', 'store')->name('api.users.store');
});

Route::controller(TenantAuthController::class)->group(function (): void {
    Route::post('/tenant/login', 'login')
        ->middleware(['throttle:10,1', ResolveTenant::class])
        ->name('api.tenant.login');

    Route::get('/tenant/csrf-token', 'csrfToken')
        ->middleware('web')
        ->name('api.tenant.csrf-token');
});

Route::middleware(AuthenticateTenantToken::class)->group(function (): void {
    Route::controller(TenantProductController::class)->group(function (): void {
        Route::get('/tenant/products', 'index')->name('api.tenant.products.index');
        Route::get('/tenant/products/create', 'create')->name('api.tenant.products.create');
        Route::post('/tenant/products', 'store')->name('api.tenant.products.store');
        Route::get('/tenant/products/{product}', 'show')->name('api.tenant.products.show');
        Route::get('/tenant/products/{product}/edit', 'edit')->name('api.tenant.products.edit');
        Route::match(['put', 'patch'], '/tenant/products/{product}', 'update')->name('api.tenant.products.update');
        Route::delete('/tenant/products/{product}', 'destroy')->name('api.tenant.products.destroy');
    });
    Route::controller(TenantCatalogController::class)->group(function (): void {
        Route::get('/tenant/catalog-settings', 'index')->name('api.tenant.catalog.index');
        Route::post('/tenant/catalog-settings/tags', 'saveTag')->name('api.tenant.catalog.tags');
        Route::post('/tenant/catalog-settings/attributes', 'saveAttribute')->name('api.tenant.catalog.attributes');
    });
    Route::controller(TenantCategoryController::class)->group(function (): void {
        Route::get('/tenant/categories', 'index')->name('api.tenant.categories.index');
        Route::get('/tenant/categories/create', 'create')->name('api.tenant.categories.create');
        Route::post('/tenant/categories', 'store')->name('api.tenant.categories.store');
        Route::get('/tenant/categories/{category}', 'show')->name('api.tenant.categories.show');
        Route::get('/tenant/categories/{category}/edit', 'edit')->name('api.tenant.categories.edit');
        Route::match(['put', 'patch'], '/tenant/categories/{category}', 'update')->name('api.tenant.categories.update');
    });

    Route::controller(TenantUserController::class)->group(function (): void {
        Route::get('/tenant/users', 'index')->name('api.tenant.users.index');
        Route::post('/tenant/users', 'store')->name('api.tenant.users.store');
        Route::get('/tenant/users/{user}', 'show')->name('api.tenant.users.show');
        Route::put('/tenant/users/{user}', 'update')->name('api.tenant.users.update');
        Route::delete('/tenant/users/{user}', 'destroy')->name('api.tenant.users.destroy');
    });

    Route::controller(TenantAuthController::class)->group(function (): void {
        Route::post('/tenant/logout', 'logout')->name('api.tenant.logout');
        Route::post('/tenant/change-password', 'changePassword')->name('api.tenant.password.change');
    });
});
