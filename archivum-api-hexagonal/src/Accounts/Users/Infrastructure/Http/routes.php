<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Http\Controllers\AccountController;

// No authentication: any visitor may open a customer account. The IP rate limit arrives with #108.
Route::post('cuentas', [AccountController::class, 'store'])->name('accounts.users.store');
