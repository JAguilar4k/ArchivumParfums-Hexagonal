<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\ScheduleBlocks\Infrastructure\Http\Controllers\ScheduleBlockController;

// The business always comes from the session of the panel: business:staff resolves it and keeps the transaction of the
// business, and business:configure keeps the routes to the owner, the only role that receives that ability in the
// permission matrix of #102. A block of another business is not found, never forbidden, so the answer never reveals that
// it exists.
Route::prefix('panel/bloqueos')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('scheduling.schedule_blocks.')
    ->controller(ScheduleBlockController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('/{bloqueo}', 'update')->whereUuid('bloqueo')->name('update');
        Route::delete('/{bloqueo}', 'destroy')->whereUuid('bloqueo')->name('destroy');
    });
