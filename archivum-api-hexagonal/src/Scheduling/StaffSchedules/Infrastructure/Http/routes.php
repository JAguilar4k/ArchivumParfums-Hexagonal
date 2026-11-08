<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\StaffSchedules\Infrastructure\Http\Controllers\StaffScheduleController;

// The business always comes from the session of the panel: business:staff resolves it and keeps the transaction of the
// business, and role:owner keeps the route to the owner until #102 defines the abilities. A person of another business
// is not found, never forbidden, so the answer never reveals that she exists.
Route::prefix('panel/personal/{persona}/horario')
    ->middleware(['auth:sanctum', 'business:staff', 'role:owner'])
    ->whereUuid('persona')
    ->name('scheduling.staff_schedules.')
    ->controller(StaffScheduleController::class)
    ->group(function (): void {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'replace')->name('replace');
    });
