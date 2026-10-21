<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Scheduling\Services\Infrastructure\Http\Controllers\ServiceController;

Route::middleware(['auth:sanctum', 'role:owner'])->prefix('panel')->group(function () {
    Route::get('/servicios', [ServiceController::class, 'index'])->name('scheduling.services.index');
    Route::post('/servicios', [ServiceController::class, 'store'])->name('scheduling.services.store');
    Route::get('/servicios/{servicio}', [ServiceController::class, 'show'])->name('scheduling.services.show');
    Route::patch('/servicios/{servicio}', [ServiceController::class, 'update'])->name('scheduling.services.update');
});
