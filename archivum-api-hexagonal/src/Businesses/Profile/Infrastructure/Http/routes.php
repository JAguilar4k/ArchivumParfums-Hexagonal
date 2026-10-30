<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Businesses\Profile\Infrastructure\Http\Controllers\BusinessProfileController;

Route::prefix('panel/tienda')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('businesses.profile.')
    ->controller(BusinessProfileController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::patch('/', 'update')->name('update');
    });
