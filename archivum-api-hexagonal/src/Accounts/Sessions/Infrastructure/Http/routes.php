<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\CurrentAccountController;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\SessionController;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\SessionRenewalController;

Route::prefix('sesiones')
    ->name('accounts.sessions.')
    ->controller(SessionController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::delete('actual', 'destroy')->middleware('auth:sanctum')->name('destroy');
    });

Route::post('sesiones/actual/renovacion', [SessionRenewalController::class, 'store'])
    ->middleware('auth:sanctum')
    ->name('accounts.sessions.renewal.store');

Route::prefix('cuenta')
    ->middleware('auth:sanctum')
    ->name('accounts.current.')
    ->controller(CurrentAccountController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
    });
