<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Payments\Payments\Infrastructure\Http\Controllers\PaymentController;

Route::prefix('cuenta')->middleware(['auth:sanctum', 'role:customer', 'business:account,context'])->name('payments.')->group(function (): void {
    Route::post('pedidos/{pedido}/pagos', [PaymentController::class, 'store'])->whereUuid('pedido')->name('store');
    Route::get('pagos/{pago}', [PaymentController::class, 'show'])->whereUuid('pago')->name('show');
});

Route::post('panel/pedidos/{pedido}/reembolsos', [PaymentController::class, 'refund'])
    ->middleware(['auth:sanctum', 'business:staff,context', 'role:owner'])->whereUuid('pedido')->name('payments.refunds.process');
