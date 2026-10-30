<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Businesses\Members\Infrastructure\Http\Controllers\MemberController;

Route::prefix('panel/personal')
    ->middleware(['auth:sanctum', 'abilities:business:configure', 'business:staff'])
    ->name('businesses.members.')
    ->controller(MemberController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{persona}', 'show')->whereUuid('persona')->name('show');
        Route::patch('/{persona}', 'update')->whereUuid('persona')->name('update');
    });
