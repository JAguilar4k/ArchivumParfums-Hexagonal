<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Http\Controllers\AccountController;
use Src\Shared\Infrastructure\RateLimitsServiceProvider;

// No authentication: any visitor may open a customer account, within the limit by IP.
Route::post('cuentas', [AccountController::class, 'store'])
    ->middleware('throttle:'.RateLimitsServiceProvider::SIGN_UP)
    ->name('accounts.users.store');
