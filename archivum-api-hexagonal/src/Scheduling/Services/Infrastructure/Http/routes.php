<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Services\Infrastructure\Http\Controllers\ServiceController;

// The business always comes from the session of the panel: business:staff resolves it and keeps the transaction of
// the business, as panel/perfumes does, and role:owner keeps the routes to the owner until #102 defines the abilities.
Route::prefix('panel/servicios')
    ->middleware(['auth:sanctum', 'business:staff', 'role:owner'])
    ->name('scheduling.services.')
    ->controller(ServiceController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{servicio}', 'show')->whereUuid('servicio')->name('show');
        Route::patch('/{servicio}', 'update')->whereUuid('servicio')->name('update');
    });
