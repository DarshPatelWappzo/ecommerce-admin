<?php

use App\Http\Controllers\Api\Tenant\TenantAuthController;
use App\Http\Controllers\Api\Tenant\TenantCategoryController;
use App\Http\Controllers\Api\Tenant\TenantCustomerAddressController;
use App\Http\Controllers\Api\Tenant\TenantCustomerController;
use App\Http\Controllers\Api\Tenant\TenantOrderController;
use App\Http\Controllers\Api\Tenant\TenantProductController;
use App\Http\Controllers\Api\Tenant\TenantUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\OrderPaymentWebhookController;
use App\Http\Controllers\TenantCatalogController;
use App\Http\Controllers\TenantCouponController;
use App\Http\Controllers\TenantPaymentController;
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
    Route::patch('tenant/coupons/{coupon}/status', [TenantCouponController::class, 'status'])->name('api.tenant.coupons.status');
    Route::controller(TenantPaymentController::class)->group(function (): void {
        Route::get('tenant/payments/methods', 'methods')->name('api.tenant.payments.methods');
        Route::get('tenant/payments', 'index')->name('api.tenant.payments.index');
        Route::get('tenant/payments/{payment}', 'show')->whereNumber('payment')->name('api.tenant.payments.show');
        Route::get('tenant/orders/{order}/payment-status', 'status')->whereNumber('order')->name('api.tenant.orders.payment-status');
        Route::post('tenant/orders/{order}/payments/initiate', 'initiate')->whereNumber('order')->name('api.tenant.orders.payments.initiate');
        Route::post('tenant/orders/{order}/payments/verify', 'verify')->whereNumber('order')->name('api.tenant.orders.payments.verify');
        Route::post('tenant/orders/{order}/payments/collect-cod', 'collect')->whereNumber('order')->name('api.tenant.orders.payments.collect-cod');
    });
    Route::apiResource('tenant/coupons', TenantCouponController::class)->names('api.tenant.coupons');
    Route::prefix('tenant/orders')->name('api.tenant.orders.')->where(['order' => '[0-9]+'])->controller(TenantOrderController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/options', 'options')->name('options');
        Route::post('/preview', 'preview')->name('preview');
        Route::get('/{order}', 'show')->name('show');
        Route::patch('/{order}', 'update')->name('update');
        Route::patch('/{order}/coupon', 'update')->name('coupon.apply');
        Route::delete('/{order}/coupon', 'removeCoupon')->name('coupon.remove');
        Route::post('/{order}/confirm', 'confirm')->name('confirm');
        Route::post('/{order}/process', 'process')->name('process');
        Route::post('/{order}/cancel', 'cancel')->name('cancel');
        Route::post('/{order}/payments', 'payments')->name('payments');
        Route::post('/{order}/shipments', 'ship')->name('ship');
        Route::post('/{order}/deliver', 'deliver')->name('deliver');
    });
    Route::prefix('tenant/customers')->name('api.tenant.customers.')->where(['customer' => '[0-9]+', 'address' => '[0-9]+'])->group(function (): void {
        Route::controller(TenantCustomerController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{customer}', 'show')->name('show');
            Route::match(['put', 'patch'], '/{customer}', 'update')->name('update');
            Route::patch('/{customer}/status', 'status')->name('status');
            Route::delete('/{customer}', 'destroy')->name('destroy');
        });
        Route::controller(TenantCustomerAddressController::class)->group(function (): void {
            Route::get('/{customer}/addresses', 'index')->name('addresses.index');
            Route::post('/{customer}/addresses', 'store')->name('addresses.store');
            Route::match(['put', 'patch'], '/{customer}/addresses/{address}', 'update')->name('addresses.update');
            Route::patch('/{customer}/addresses/{address}/default', 'defaults')->name('addresses.default');
            Route::delete('/{customer}/addresses/{address}', 'destroy')->name('addresses.destroy');
        });
    });
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

Route::post('tenant/payments/razorpay/webhook', OrderPaymentWebhookController::class)->name('api.tenant.payments.webhook');
