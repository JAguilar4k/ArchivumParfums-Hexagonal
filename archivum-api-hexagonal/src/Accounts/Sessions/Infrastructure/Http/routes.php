<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\CurrentAccountController;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\SessionController;

Route::prefix('sesiones')
    ->name('accounts.sessions.')
    ->controller(SessionController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });

Route::prefix('cuenta')
    ->middleware('auth:sanctum')
    ->name('accounts.current.')
    ->controller(CurrentAccountController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
    });
