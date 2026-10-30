<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Sales\Orders\Infrastructure\Http\Controllers\BusinessOrderController;
use Src\Sales\Orders\Infrastructure\Http\Controllers\CustomerOrderController;

Route::post('tiendas/{tienda}/pedidos', [CustomerOrderController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:customer:own', 'business:route'])->name('sales.customer.orders.store');

Route::prefix('cuenta/pedidos')->middleware(['auth:sanctum', 'abilities:customer:own'])->name('sales.customer.orders.')
    ->controller(CustomerOrderController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{pedido}', 'show')->whereUuid('pedido')->middleware('business:account')->name('show');
        Route::post('/{pedido}/cancelacion', 'cancel')->whereUuid('pedido')->middleware('business:account')->name('cancel');
    });

Route::prefix('panel/pedidos')->middleware(['auth:sanctum', 'abilities:orders:manage', 'business:staff'])->name('sales.business.orders.')
    ->controller(BusinessOrderController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{pedido}', 'show')->whereUuid('pedido')->name('show');
        Route::post('/{pedido}/preparacion', 'prepare')->whereUuid('pedido')->name('prepare');
        Route::post('/{pedido}/listo', 'ready')->whereUuid('pedido')->name('ready');
        Route::post('/{pedido}/entrega', 'deliver')->whereUuid('pedido')->name('deliver');
        Route::post('/{pedido}/lineas/{linea}/no-disponible', 'unavailable')->whereUuid(['pedido', 'linea'])->name('unavailable');
    });
