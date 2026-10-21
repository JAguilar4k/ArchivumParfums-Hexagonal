<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Illuminate\Support\Facades\Route;
use Src\Businesses\Logo\Infrastructure\LogoServiceProvider;
use Src\Businesses\Profile\Infrastructure\ProfileEventsServiceProvider;
use Src\Businesses\PublicProfile\Infrastructure\PublicProfileServiceProvider;
use Src\Catalog\Perfumes\Infrastructure\PerfumesServiceProvider;
use Src\Catalog\Showcase\Infrastructure\ShowcaseServiceProvider;

/**
 * Put the catalog in the application the way the framework would once its providers and its routes are registered
 * in bootstrap/providers.php and routes/api.php: the same prefix and the same middleware group as every other route.
 */
final class ServesCatalog
{
    /** Files of routes of the modules that this support serves, relative to the base path. */
    private const array ROUTES = [
        'src/Catalog/Perfumes/Infrastructure/Http/routes.php',
        'src/Businesses/Logo/Infrastructure/Http/routes.php',
        'src/Businesses/PublicProfile/Infrastructure/Http/routes.php',
        'src/Catalog/Showcase/Infrastructure/Http/routes.php',
    ];

    /** Register the providers of the modules and load their routes under /api/v1. */
    public static function register(): void
    {
        app()->register(PerfumesServiceProvider::class);
        app()->register(ProfileEventsServiceProvider::class);
        app()->register(LogoServiceProvider::class);
        app()->register(PublicProfileServiceProvider::class);
        app()->register(ShowcaseServiceProvider::class);

        foreach (self::ROUTES as $routes) {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path($routes));
        }

        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }
}
