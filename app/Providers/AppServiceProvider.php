<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Services\OrderPaymentGateway;
use App\Services\RazorpayOrderGateway;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrderPaymentGateway::class, RazorpayOrderGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Paginator::useBootstrapFive();
        Schema::defaultStringLength(191);
    }
}
