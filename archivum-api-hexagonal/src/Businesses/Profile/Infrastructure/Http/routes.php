<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Businesses\Profile\Infrastructure\Http\Controllers\BusinessProfileController;
use Src\Businesses\Profile\Infrastructure\Http\Controllers\PlatformBusinessController;

Route::prefix('panel/tienda')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('businesses.profile.')
    ->controller(BusinessProfileController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'update')->name('update');
    });

// Businesses of the whole platform (#347): only the token of an administrator of the platform has this ability.
Route::prefix('plataforma/tiendas')
    ->middleware(['auth:sanctum', 'abilities:platform:businesses'])
    ->name('businesses.platform.')
    ->controller(PlatformBusinessController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
    });
