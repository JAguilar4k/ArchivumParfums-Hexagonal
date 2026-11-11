<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Src\Recommendations\Recommender\Infrastructure\Http\Controllers\RecommendationController;
use Src\Shared\Infrastructure\RateLimitsServiceProvider;

// No authentication: a visitor, a customer and any member of a store use it alike, and nothing of the account is read.
// The store comes from the slug of the address and only serves to read its showcase. business:route resolves it without
// a transaction (context), because the AI may take up to its limit to answer: the catalog is read in a transaction of
// its own. The limit of requests is the one of #108, by account or by IP.
Route::post('tiendas/{tienda}/recomendaciones', [RecommendationController::class, 'store'])
    ->middleware(['business:route,context', 'throttle:'.RateLimitsServiceProvider::RECOMMENDATIONS])
    ->name('recommendations.store');
