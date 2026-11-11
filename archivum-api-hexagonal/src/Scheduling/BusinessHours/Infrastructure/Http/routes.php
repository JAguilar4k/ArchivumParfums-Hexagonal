<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\BusinessHours\Infrastructure\Http\Controllers\BusinessHoursController;

// The business always comes from the session of the panel: business:staff resolves it and keeps the transaction of the
// business, as panel/perfumes does, and business:configure keeps the route to the owner, the only role that receives
// that ability in the permission matrix of #102.
Route::prefix('panel/horario')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('scheduling.business_hours.')
    ->controller(BusinessHoursController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'replace')->name('replace');
    });
