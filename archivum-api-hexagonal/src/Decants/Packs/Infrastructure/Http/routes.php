<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Decants\Packs\Infrastructure\Http\Controllers\CustomerPackController;
use Src\Decants\Packs\Infrastructure\Http\Controllers\DecantPackPlanController;
use Src\Decants\Packs\Infrastructure\Http\Controllers\PackPurchaseController;
use Src\Decants\Packs\Infrastructure\Http\Controllers\PublicPackPlanController;
use Src\Decants\Packs\Infrastructure\Http\Controllers\SoldPackController;

// Only the owner defines the offers (section 9): the staff and the customers lack packs:manage and get a 403.
Route::prefix('panel/paquetes')
    ->middleware(['auth:sanctum', 'abilities:packs:manage', 'business:staff'])
    ->name('decants.packs.plans.')
    ->controller(DecantPackPlanController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::patch('/{paquete}', 'update')->whereUuid('paquete')->name('update');
    });

// Only the owner sees the packs sold in the store, like the offers: the staff and the customers lack packs:manage and get
// a 403. The store comes from the session (R54).
Route::get('panel/paquetes-vendidos', [SoldPackController::class, 'index'])
    ->middleware(['auth:sanctum', 'abilities:packs:manage', 'business:staff'])
    ->name('decants.packs.sold.index');

// No authentication: any visitor may see the packs on sale. The store comes from the slug of the address and only serves
// to read its own offers; business:route resolves it and pins the transaction to it for the row-level policies.
Route::get('tiendas/{tienda}/paquetes', [PublicPackPlanController::class, 'index'])
    ->middleware('business:route')
    ->name('decants.packs.on-sale.index');

// Only a customer buys (section 9): without a session 401, and the staff, without customer:own, 403. The store comes from
// the slug of the address. business:route only resolves it, without a transaction around the request: like a payment
// (#92), the purchase commits the pack and its payment attempt before it asks the gateway for the charge.
Route::post('tiendas/{tienda}/paquetes/{paquete}/compra', [PackPurchaseController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:customer:own', 'business:route,context'])
    ->whereUuid('paquete')
    ->name('decants.packs.purchases.store');

// Only a customer reads its packs: without a session 401, and the staff, without customer:own, 403. The address names no
// store: the controller reads each store where the account is a customer under its own row-level scope, like the orders
// of the account (#32).
Route::get('cuenta/paquetes-comprados', [CustomerPackController::class, 'index'])
    ->middleware(['auth:sanctum', 'abilities:customer:own'])
    ->name('decants.packs.purchased.index');
