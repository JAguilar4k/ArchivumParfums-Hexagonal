<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Payments\Payments\Infrastructure\Http\Controllers\PaymentController;
use Src\Payments\Payments\Infrastructure\Http\Middleware\AuthorizePayment;
use Src\Sales\Orders\Infrastructure\Http\Middleware\AuthorizeOrder;

Route::prefix('cuenta')->middleware(['auth:sanctum', 'abilities:customer:own', 'business:account,context'])->name('payments.')->group(function (): void {
    Route::post('pedidos/{pedido}/pagos', [PaymentController::class, 'store'])->whereUuid('pedido')->middleware(AuthorizeOrder::class.':pay')->name('store');
    Route::get('pagos/{pago}', [PaymentController::class, 'show'])->whereUuid('pago')->middleware(AuthorizePayment::class.':view')->name('show');
});

Route::post('panel/pedidos/{pedido}/reembolsos', [PaymentController::class, 'refund'])
    ->middleware(['auth:sanctum', 'abilities:payments:record', 'business:staff,context', AuthorizeOrder::class.':manage'])->whereUuid('pedido')->name('payments.refunds.process');
