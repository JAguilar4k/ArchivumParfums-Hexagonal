<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\CurrentAccountController;
use Src\Accounts\Sessions\Infrastructure\Http\Controllers\SessionController;

Route::post('sesiones', [SessionController::class, 'store'])->name('accounts.sessions.store');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('cuenta', [CurrentAccountController::class, 'show'])->name('accounts.current.show');
});
