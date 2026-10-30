<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Businesses\Logo\Infrastructure\Http\Controllers\BusinessLogoController;

Route::prefix('panel/tienda/logo')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('businesses.logo.')
    ->controller(BusinessLogoController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
    });
