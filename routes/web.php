<?php

use App\Http\Controllers\SuperAdmin\AuditLogController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\PackageController;
use App\Http\Controllers\SuperAdmin\TenantProvisioningController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\TenantAuditLogController;
use App\Http\Controllers\TenantAuthController;
use App\Http\Controllers\TenantCatalogController;
use App\Http\Controllers\TenantCategoryController;
use App\Http\Controllers\TenantCouponController;
use App\Http\Controllers\TenantCustomerAddressController;
use App\Http\Controllers\TenantCustomerController;
use App\Http\Controllers\TenantInvoiceController;
use App\Http\Controllers\TenantOrderController;
use App\Http\Controllers\TenantPaymentController;
use App\Http\Controllers\TenantProductController;
use App\Http\Controllers\TenantReplacementController;
use App\Http\Controllers\TenantReturnController;
use App\Http\Controllers\TenantRoleController;
use App\Http\Controllers\TenantTaxController;
use App\Http\Controllers\TenantUserController;
use App\Http\Middleware\EnsureActiveTenantUser;
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

    Route::middleware([ResolveTenant::class, 'auth:tenant', EnsureActiveTenantUser::class])->group(function (): void {
        Route::get('/change-password', [TenantAuthController::class, 'passwordForm'])->name('password.form');
        Route::post('/change-password', [TenantAuthController::class, 'changePassword'])->name('password.change');
        Route::post('/logout', [TenantAuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', [TenantAuthController::class, 'dashboard'])
            ->middleware(RequireTenantPasswordChange::class)
            ->name('dashboard');

        Route::middleware(RequireTenantPasswordChange::class)->group(function (): void {
            Route::get('audit-logs', [TenantAuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-logs/{auditLog}', [TenantAuditLogController::class, 'show'])->whereNumber('auditLog')->name('audit-logs.show');
            Route::prefix('replacements')->name('replacements.')->controller(TenantReplacementController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{replacement}', 'show')->whereNumber('replacement')->name('show');
                Route::post('/{replacement}/transition', 'transition')->whereNumber('replacement')->name('transition');
                Route::post('/{replacement}/refund', 'refund')->whereNumber('replacement')->name('refund');
            });
            Route::prefix('returns')->name('returns.')->controller(TenantReturnController::class)->group(function (): void {
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
            Route::resource('invoices', TenantInvoiceController::class)->except('destroy');
            Route::post('invoices/{invoice}/issue', [TenantInvoiceController::class, 'issue'])->whereNumber('invoice')->name('invoices.issue');
            Route::get('invoices/{invoice}/pdf', [TenantInvoiceController::class, 'pdf'])->whereNumber('invoice')->name('invoices.pdf');
            Route::get('invoices/{invoice}/print', [TenantInvoiceController::class, 'print'])->whereNumber('invoice')->name('invoices.print');
            Route::post('orders/{order}/approve-dispatch', [TenantOrderController::class, 'approveDispatch'])->whereNumber('order')->name('orders.approve-dispatch');
            Route::controller(TenantPaymentController::class)->group(function (): void {
                Route::get('payments', 'index')->name('payments.index');
                Route::get('payments/{payment}', 'show')->whereNumber('payment')->name('payments.show');
                Route::post('orders/{order}/payments/collect-cod', 'collect')->whereNumber('order')->name('orders.payments.collect-cod');
                Route::post('orders/{order}/payments/initiate', 'initiate')->whereNumber('order')->name('orders.payments.initiate');
                Route::post('orders/{order}/payments/verify', 'verify')->whereNumber('order')->name('orders.payments.verify');
                Route::post('orders/{order}/payments/reconcile', 'reconcile')->whereNumber('order')->middleware('throttle:10,1')->name('orders.payments.reconcile');
                Route::get('orders/{order}/payment-status', 'status')->whereNumber('order')->name('orders.payment-status');
            });
            Route::patch('coupons/{coupon}/status', [TenantCouponController::class, 'status'])->name('coupons.status');
            Route::resource('coupons', TenantCouponController::class);
            Route::prefix('orders')->name('orders.')->where(['order' => '[0-9]+'])->controller(TenantOrderController::class)->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/options', 'options')->name('options');
                Route::post('/preview', 'preview')->name('preview');
                Route::get('/create', 'create')->name('create');
                Route::get('/{order}/edit', 'edit')->name('edit');
                Route::get('/{order}', 'show')->name('show');
                Route::patch('/{order}', 'update')->name('update');
                Route::post('/{order}/confirm', 'confirm')->name('confirm');
                Route::post('/{order}/process', 'process')->name('process');
                Route::post('/{order}/cancel', 'cancel')->name('cancel');
                Route::post('/{order}/payments', 'payments')->name('payments');
                Route::post('/{order}/shipments', 'ship')->name('ship');
                Route::post('/{order}/deliver', 'deliver')->name('deliver');
            });
            Route::prefix('customers')->name('customers.')->where(['customer' => '[0-9]+', 'address' => '[0-9]+'])->group(function (): void {
                Route::controller(TenantCustomerController::class)->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/{customer}', 'show')->name('show');
                    Route::get('/{customer}/edit', 'edit')->name('edit');
                    Route::match(['put', 'patch'], '/{customer}', 'update')->name('update');
                    Route::patch('/{customer}/status', 'status')->name('status');
                    Route::delete('/{customer}', 'destroy')->name('destroy');
                });
                Route::controller(TenantCustomerAddressController::class)->group(function (): void {
                    Route::get('/{customer}/addresses/create', 'create')->name('addresses.create');
                    Route::post('/{customer}/addresses', 'store')->name('addresses.store');
                    Route::get('/{customer}/addresses/{address}/edit', 'edit')->name('addresses.edit');
                    Route::match(['put', 'patch'], '/{customer}/addresses/{address}', 'update')->name('addresses.update');
                    Route::patch('/{customer}/addresses/{address}/default', 'defaults')->name('addresses.default');
                    Route::delete('/{customer}/addresses/{address}', 'destroy')->name('addresses.destroy');
                });
            });
            Route::controller(TenantProductController::class)->group(function (): void {
                Route::get('/products', 'index')->name('products.index');
                Route::get('/products/create', 'create')->name('products.create');
                Route::post('/products', 'store')->name('products.store');
                Route::get('/products/{product}/edit', 'edit')->name('products.edit');
                Route::put('/products/{product}', 'update')->name('products.update');
                Route::delete('/products/{product}', 'destroy')->name('products.destroy');
            });
            Route::controller(TenantCatalogController::class)->group(function (): void {
                Route::get('/catalog-settings', 'index')->name('catalog.index');
                Route::post('/catalog-settings/tags', 'saveTag')->name('catalog.tags');
                Route::post('/catalog-settings/attributes', 'saveAttribute')->name('catalog.attributes');
            });
            Route::resource('categories', TenantCategoryController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('categories');
            Route::resource('taxes', TenantTaxController::class)->only(['index', 'create', 'store', 'edit', 'update'])->names('taxes');
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
