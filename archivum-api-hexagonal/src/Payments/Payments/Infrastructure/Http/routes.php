<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Payments\Payments\Infrastructure\Http\Controllers\PaymentController;
use Src\Payments\Payments\Infrastructure\Http\Controllers\PaymentWebhookController;

Route::prefix('cuenta')->middleware(['auth:sanctum', 'abilities:customer:own', 'business:account,context'])->name('payments.')->group(function (): void {
    Route::post('pedidos/{pedido}/pagos', [PaymentController::class, 'store'])->whereUuid('pedido')->name('store');
    Route::get('pagos/{pago}', [PaymentController::class, 'show'])->whereUuid('pago')->name('show');
});

// Nothing leaves for a gateway, so the whole request runs in one transaction of the business.
Route::post('panel/pedidos/{pedido}/pagos-manuales', [PaymentController::class, 'storeManual'])
    ->middleware(['auth:sanctum', 'abilities:payments:record', 'business:staff'])->whereUuid('pedido')->name('payments.manual.store');

Route::post('panel/pedidos/{pedido}/reembolsos', [PaymentController::class, 'refund'])
    ->middleware(['auth:sanctum', 'abilities:payments:record', 'business:staff,context'])->whereUuid('pedido')->name('payments.refunds.process');

// A notice of the provider has no session or token: ReceivePaymentEvent verifies its origin before anything else.
Route::prefix('webhooks')->name('payments.webhooks.')->group(function (): void {
    Route::post('onvo', [PaymentWebhookController::class, 'onvo'])->name('onvo');
    Route::post('paypal', [PaymentWebhookController::class, 'paypal'])->name('paypal');
});
