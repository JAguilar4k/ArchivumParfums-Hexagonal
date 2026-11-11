<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Appointments\Infrastructure\Http\Controllers\CustomerAppointmentController;

// Only the customer of the session books, and only in the store of the address: business:route resolves it, pins the
// transaction for the row-level policies and the abuse of another store never reaches its rows.
Route::post('tiendas/{tienda}/citas', [CustomerAppointmentController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:customer:own', 'business:route'])
    ->name('scheduling.customer.appointments.store');

// The customer reads only its own appointments: the listing walks the businesses where the account is a customer and
// the detail resolves the store of that appointment from the session, never from an identity the browser sends.
Route::prefix('cuenta/citas')->middleware(['auth:sanctum', 'abilities:customer:own'])
    ->name('scheduling.customer.appointments.')
    ->controller(CustomerAppointmentController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{cita}', 'show')->whereUuid('cita')->middleware('business:account')->name('show');
    });
