<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Perfumes\Infrastructure\Http\Controllers\PerfumeController;

Route::prefix('panel/perfumes')
    ->middleware(['auth:sanctum', 'business:staff', 'role:owner'])
    ->name('catalog.perfumes.')
    ->controller(PerfumeController::class)
    ->group(function (): void {
        Route::post('/', 'store')->name('store');
        Route::patch('/{perfume}', 'update')->whereUuid('perfume')->name('update');
    });
