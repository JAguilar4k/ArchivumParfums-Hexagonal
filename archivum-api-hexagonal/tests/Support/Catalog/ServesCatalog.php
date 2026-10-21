<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Illuminate\Support\Facades\Route;
use Src\Businesses\Profile\Infrastructure\ProfileEventsServiceProvider;
use Src\Catalog\Perfumes\Infrastructure\PerfumesServiceProvider;

/**
 * Put the catalog in the application the way the framework would once its provider and its routes are registered
 * in bootstrap/providers.php and routes/api.php: the same prefix and the same middleware group as every other route.
 */
final class ServesCatalog
{
    /** Register the provider of the module and load its routes under /api/v1. */
    public static function register(): void
    {
        app()->register(PerfumesServiceProvider::class);
        app()->register(ProfileEventsServiceProvider::class);

        Route::middleware('api')
            ->prefix('api/v1')
            ->group(base_path('src/Catalog/Perfumes/Infrastructure/Http/routes.php'));

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
