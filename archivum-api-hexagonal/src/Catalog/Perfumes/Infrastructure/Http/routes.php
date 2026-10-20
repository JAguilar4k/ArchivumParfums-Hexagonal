<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Perfumes\Infrastructure\Http\Controllers\PerfumeController;

Route::prefix('panel/perfumes')
    ->middleware(['auth:sanctum', 'business:staff', 'role:owner'])
    ->name('catalog.perfumes.')
    ->controller(PerfumeController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{perfume}', 'show')->whereUuid('perfume')->name('show');
        Route::patch('/{perfume}', 'update')->whereUuid('perfume')->name('update');
    });
