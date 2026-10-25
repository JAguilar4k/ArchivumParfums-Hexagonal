<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Decants\Bottles\Infrastructure\Http\Controllers\DecantBottleController;
use Src\Decants\Bottles\Infrastructure\Http\Controllers\DecantMovementController;

Route::prefix('panel/botellas')->middleware(['auth:sanctum', 'business:staff', 'role:owner'])->name('decants.bottles.')->group(function (): void {
    Route::get('/', [DecantBottleController::class, 'index'])->name('index');
    Route::post('/', [DecantBottleController::class, 'store'])->name('store');
    Route::get('/{botella}', [DecantBottleController::class, 'show'])->whereUuid('botella')->name('show');
    Route::patch('/{botella}', [DecantBottleController::class, 'update'])->whereUuid('botella')->name('update');
    Route::get('/{botella}/movimientos', [DecantMovementController::class, 'index'])->whereUuid('botella')->name('movements.index');
    Route::post('/{botella}/ajustes', [DecantMovementController::class, 'store'])->whereUuid('botella')->name('adjustments.store');
});
