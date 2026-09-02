<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Middleware\EnsureSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSuperAdmin::class])->group(function (): void {
    Route::post('/users', [UserController::class, 'store'])->name('api.users.store');
});
