<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Perfumes\Infrastructure\Http\Controllers\PerfumeController;
use Src\Catalog\Perfumes\Infrastructure\Http\Controllers\PerfumePhotoController;
use Src\Catalog\Perfumes\Infrastructure\Http\Controllers\PerfumeVariantController;

Route::prefix('panel/perfumes')
    ->middleware(['auth:sanctum', 'abilities:catalog:manage', 'business:staff'])
    ->name('catalog.perfumes.')
    ->group(function (): void {
        Route::controller(PerfumeController::class)->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/{perfume}', 'show')->whereUuid('perfume')->name('show');
            Route::patch('/{perfume}', 'update')->whereUuid('perfume')->name('update');
        });

        Route::post('/{perfume}/foto', [PerfumePhotoController::class, 'store'])->whereUuid('perfume')->name('photo.store');

        Route::controller(PerfumeVariantController::class)
            ->prefix('{perfume}/presentaciones')
            ->whereUuid('perfume')
            ->name('variants.')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::patch('/{presentacion}', 'update')->whereUuid('presentacion')->name('update');
            });
    });
