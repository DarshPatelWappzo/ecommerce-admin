<?php

use App\Http\Controllers\Api\Tenant\TenantAuthController;
use App\Http\Controllers\Api\Tenant\TenantCategoryController;
use App\Http\Controllers\Api\Tenant\TenantCustomerAddressController;
use App\Http\Controllers\Api\Tenant\TenantCustomerController;
use App\Http\Controllers\Api\Tenant\TenantOrderController;
use App\Http\Controllers\Api\Tenant\TenantProductController;
use App\Http\Controllers\Api\Tenant\TenantUserController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerCartController;
use App\Http\Controllers\CustomerCatalogController;
use App\Http\Controllers\CustomerCheckoutController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\CustomerReplacementController;
use App\Http\Controllers\CustomerReturnController;
use App\Http\Controllers\CustomerWishlistController;
use App\Http\Controllers\OrderPaymentWebhookController;
use App\Http\Controllers\TenantAuditLogController;
use App\Http\Controllers\TenantCatalogController;
use App\Http\Controllers\TenantCouponController;
use App\Http\Controllers\TenantInvoiceController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\TenantReplacementController;
use App\Http\Controllers\TenantReturnController;
use App\Http\Middleware\AuthenticateCustomerToken;
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
    Route::get('tenant/audit-logs', [TenantAuditLogController::class, 'index'])->name('api.tenant.audit-logs.index');
    Route::get('tenant/audit-logs/{auditLog}', [TenantAuditLogController::class, 'show'])->whereNumber('auditLog')->name('api.tenant.audit-logs.show');
    Route::prefix('tenant/replacements')->name('api.tenant.replacements.')->controller(TenantReplacementController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{replacement}', 'show')->whereNumber('replacement')->name('show');
        Route::post('/{replacement}/transition', 'transition')->whereNumber('replacement')->name('transition');
        Route::post('/{replacement}/refund', 'refund')->whereNumber('replacement')->name('refund');
    });
    Route::prefix('tenant/returns')->name('api.tenant.returns.')->controller(TenantReturnController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/reasons', 'reasons')->name('reasons');
        Route::post('/reasons', 'saveReason')->name('reasons.save');
        Route::get('/{return}', 'show')->whereNumber('return')->name('show');
        Route::post('/{return}/transition', 'transition')->whereNumber('return')->name('transition');
        Route::post('/{return}/refund', 'initiate')->whereNumber('return')->name('initiate');
        Route::post('/{return}/refund/retry', 'retry')->whereNumber('return')->name('retry');
        Route::post('/{return}/refund/reconcile', 'reconcile')->whereNumber('return')->middleware('throttle:10,1')->name('reconcile');
        Route::post('/{return}/refund/manual', 'manual')->whereNumber('return')->name('manual');
    });
    Route::apiResource('tenant/invoices', TenantInvoiceController::class)->except('destroy')->names('api.tenant.invoices');
    Route::controller(TenantInvoiceController::class)->group(function (): void {
        Route::post('tenant/invoices/{invoice}/issue', 'issue')->whereNumber('invoice')->name('api.tenant.invoices.issue');
        Route::get('tenant/invoices/{invoice}/pdf', 'pdf')->whereNumber('invoice')->name('api.tenant.invoices.pdf');
    });

    Route::post('tenant/orders/{order}/approve-dispatch', [TenantOrderController::class, 'approveDispatch'])->whereNumber('order')->name('api.tenant.orders.approve-dispatch');
    Route::patch('tenant/coupons/{coupon}/status', [TenantCouponController::class, 'status'])->name('api.tenant.coupons.status');
    Route::controller(TenantPaymentController::class)->group(function (): void {
        Route::get('tenant/payments/methods', 'methods')->name('api.tenant.payments.methods');
        Route::get('tenant/payments', 'index')->name('api.tenant.payments.index');
        Route::get('tenant/payments/{payment}', 'show')->whereNumber('payment')->name('api.tenant.payments.show');
        Route::get('tenant/orders/{order}/payment-status', 'status')->whereNumber('order')->name('api.tenant.orders.payment-status');
        Route::post('tenant/orders/{order}/payments/initiate', 'initiate')->whereNumber('order')->name('api.tenant.orders.payments.initiate');
        Route::post('tenant/orders/{order}/payments/verify', 'verify')->whereNumber('order')->name('api.tenant.orders.payments.verify');
        Route::post('tenant/orders/{order}/payments/reconcile', 'reconcile')->whereNumber('order')->middleware('throttle:10,1')->name('api.tenant.orders.payments.reconcile'); //
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

Route::prefix('customer')->name('api.customer.')->group(function (): void {
    Route::middleware(['throttle:10,1', ResolveTenant::class])->controller(CustomerAuthController::class)->group(function (): void {
        Route::post('auth/request-code', 'requestCode')->name('auth.request-code');
        Route::post('auth/verify-code', 'verify')->name('auth.verify-code');
    });
    Route::middleware([AuthenticateCustomerToken::class, 'throttle:60,1'])->group(function (): void {
        Route::controller(CustomerCartController::class)->group(function (): void {
            Route::get('cart', 'show')->name('cart.show');
            Route::post('cart/items', 'store')->name('cart.items.store');
            Route::patch('cart/items/{item}', 'update')->whereNumber('item')->name('cart.items.update');
            Route::delete('cart/items/{item}', 'destroy')->whereNumber('item')->name('cart.items.destroy');
            Route::delete('cart', 'clear')->name('cart.clear');
        });
        Route::controller(CustomerWishlistController::class)->group(function (): void {
            Route::get('wishlist', 'index')->name('wishlist.index');
            Route::post('wishlist', 'store')->name('wishlist.store');
            Route::delete('wishlist/{product}', 'destroy')->whereNumber('product')->name('wishlist.destroy');
            Route::post('wishlist/{product}/move-to-cart', 'move')->whereNumber('product')->name('wishlist.move');
        });
        Route::controller(CustomerCheckoutController::class)->group(function (): void {
            Route::get('addresses', 'addresses')->name('addresses.index');
            Route::get('checkout/summary', 'summary')->name('checkout.summary');
            Route::post('checkout/coupon', 'applyCoupon')->name('checkout.coupon.apply');
            Route::delete('checkout/coupon', 'removeCoupon')->name('checkout.coupon.remove');
            Route::post('checkout/place-order', 'place')->middleware('throttle:10,1,customer-purchase')->name('checkout.place');
        });
        Route::controller(CustomerOrderController::class)->group(function (): void {
            Route::get('orders', 'index')->name('orders.index');
            Route::get('orders/{order}', 'show')->whereNumber('order')->name('orders.show');
            Route::get('orders/{order}/tracking', 'tracking')->whereNumber('order')->name('orders.tracking');
            Route::get('orders/{order}/invoice', 'invoice')->whereNumber('order')->name('orders.invoice');
            Route::post('orders/{order}/reorder', 'reorder')->whereNumber('order')->name('orders.reorder');
            Route::post('orders/{order}/payment', 'payment')->whereNumber('order')->middleware('throttle:10,1,customer-purchase')->name('orders.payment');
        });
        Route::controller(CustomerCatalogController::class)->group(function (): void {
            Route::get('products', 'index')->name('products.index');
            Route::get('products/{product}', 'show')->name('products.show');
            Route::get('categories', 'categories')->name('categories.index');
        });
        Route::post('auth/logout', [CustomerAuthController::class, 'logout'])->name('auth.logout');
        Route::controller(CustomerReplacementController::class)->group(function (): void {
            Route::get('replacement-reasons', 'reasons')->name('replacements.reasons');
            Route::get('orders/{order}/items/{item}/replacement-eligibility', 'eligibility')->whereNumber(['order', 'item'])->name('replacements.eligibility');
            Route::post('orders/{order}/items/{item}/replacements', 'store')->whereNumber(['order', 'item'])->name('replacements.store');
            Route::get('replacements', 'index')->name('replacements.index');
            Route::get('replacements/{replacement}', 'show')->whereNumber('replacement')->name('replacements.show');
            Route::post('replacements/{replacement}/cancel', 'cancel')->whereNumber('replacement')->name('replacements.cancel');
        });
        Route::controller(CustomerReturnController::class)->group(function (): void {
            Route::get('return-reasons', 'reasons')->name('returns.reasons');
            Route::get('orders/{order}/items/{item}/return-eligibility', 'eligibility')->whereNumber(['order', 'item'])->name('returns.eligibility');
            Route::post('orders/{order}/items/{item}/returns', 'store')->whereNumber(['order', 'item'])->name('returns.store');
            Route::get('returns', 'index')->name('returns.index');
            Route::get('returns/{return}', 'show')->whereNumber('return')->name('returns.show');
        });
    });
});
