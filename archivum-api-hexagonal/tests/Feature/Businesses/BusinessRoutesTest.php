<?php

declare(strict_types=1);

use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/**
 * Registered routes whose URI starts with a prefix.
 *
 * @return list<RouteDefinition>
 */
function routesUnder(string $prefix): array
{
    return array_values(array_filter(
        Route::getRoutes()->getRoutes(),
        fn (RouteDefinition $route): bool => str_starts_with($route->uri(), $prefix),
    ));
}

it('resolves the business of every panel route from the session and never from the URL', function () {
    $violations = [];

    foreach (routesUnder('api/v1/panel') as $route) {
        $middleware = $route->gatherMiddleware();

        if (! in_array('auth:sanctum', $middleware, true) || ! in_array('business:staff', $middleware, true)) {
            $violations[] = "{$route->uri()} lacks auth:sanctum and business:staff";
        }

        if (array_intersect($route->parameterNames(), ['tienda', 'business', 'business_id']) !== []) {
            $violations[] = "{$route->uri()} takes the business from the URL";
        }
    }

    expect($violations)->toBe([]);
});

it('resolves the business of every public route of a business from its slug', function () {
    $violations = [];

    foreach (routesUnder('api/v1/tiendas/{tienda}') as $route) {
        if (! in_array('business:route', $route->gatherMiddleware(), true)) {
            $violations[] = "{$route->uri()} lacks business:route";
        }
    }

    expect($violations)->toBe([]);
});
