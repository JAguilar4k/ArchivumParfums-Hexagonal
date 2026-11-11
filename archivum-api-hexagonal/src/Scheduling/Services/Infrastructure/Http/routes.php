<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Services\Infrastructure\Http\Controllers\ServiceController;

// The business always comes from the session of the panel: business:staff resolves it and keeps the transaction of
// the business, as panel/perfumes does, and business:configure keeps the routes to the owner, which is the only role
// that receives that ability in the permission matrix of #102.
Route::prefix('panel/servicios')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('scheduling.services.')
    ->controller(ServiceController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{servicio}', 'show')->whereUuid('servicio')->name('show');
        Route::patch('/{servicio}', 'update')->whereUuid('servicio')->name('update');
    });
