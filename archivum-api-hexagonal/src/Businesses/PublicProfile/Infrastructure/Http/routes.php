<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Businesses\PublicProfile\Infrastructure\Http\Controllers\PublicBusinessController;

// No authentication: any visitor may read the profile. The business comes from the slug of the address, and the
// business:route middleware resolves it for this route and for every other public route that has {tienda}.
Route::prefix('tiendas/{tienda}')
    ->middleware('business:route')
    ->name('businesses.public.')
    ->controller(PublicBusinessController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
    });
