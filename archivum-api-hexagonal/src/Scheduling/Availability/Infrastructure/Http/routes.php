<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Availability\Infrastructure\Http\Controllers\AvailabilityController;

// No authentication: any visitor may see the free slots of a store. The store comes from the slug of the address and
// only serves to read its own agenda; business:route resolves it and pins the transaction for the row-level policies.
Route::get('tiendas/{tienda}/disponibilidad', [AvailabilityController::class, 'index'])
    ->middleware('business:route')
    ->name('scheduling.availability.index');
