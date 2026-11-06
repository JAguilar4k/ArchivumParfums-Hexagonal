<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Http\Controllers\AccountController;
use Src\Accounts\Users\Infrastructure\Http\Controllers\PasswordController;
use Src\Accounts\Users\Infrastructure\Http\Controllers\PasswordResetController;
use Src\Accounts\Users\Infrastructure\PasswordsServiceProvider;
use Src\Shared\Infrastructure\RateLimitsServiceProvider;

// No authentication: any visitor may open a customer account, within the limit by IP.
Route::post('cuentas', [AccountController::class, 'store'])
    ->middleware('throttle:'.RateLimitsServiceProvider::SIGN_UP)
    ->name('accounts.users.store');

// For the three roles, as GET /cuenta: no ability of a role and no business, because an account belongs to none.
Route::put('cuenta/contrasena', [PasswordController::class, 'update'])
    ->middleware(['auth:sanctum', 'throttle:'.PasswordsServiceProvider::CHANGE])
    ->name('accounts.password.update');

Route::prefix('recuperaciones-de-contrasena')
    ->name('accounts.password.reset.')
    ->controller(PasswordResetController::class)
    ->group(function (): void {
        Route::post('/', 'store')->middleware('throttle:'.PasswordsServiceProvider::REQUEST)->name('store');
        Route::post('confirmacion', 'confirm')->middleware('throttle:'.PasswordsServiceProvider::CONFIRM)->name('confirm');
    });
