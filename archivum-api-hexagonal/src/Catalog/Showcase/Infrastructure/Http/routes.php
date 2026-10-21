<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Catalog\Showcase\Infrastructure\Http\Controllers\ShowcaseController;

// No authentication: any visitor may browse the showcase. The store comes from the slug of the address and only serves to
// read its own showcase; business:route resolves it and pins the transaction to it for the row-level policies.
Route::prefix('tiendas/{tienda}/perfumes')
    ->middleware('business:route')
    ->name('catalog.showcase.')
    ->controller(ShowcaseController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{perfume}', 'show')->whereUuid('perfume')->name('show');
    });
