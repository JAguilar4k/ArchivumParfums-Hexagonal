<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Shared\Infrastructure\Http\Controllers\ApiIndexController;

Route::get('/', ApiIndexController::class)->name('api.index');
