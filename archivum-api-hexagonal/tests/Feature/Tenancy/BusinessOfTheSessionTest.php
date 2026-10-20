<?php

declare(strict_types=1);

use Database\Seeders\BusinessesSeeder;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Accounts\Users\Infrastructure\Tenancy\CustomerBusinessContext;
use Src\Shared\Domain\Contracts\BusinessContext;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

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

/** Whether the closure of the test panel route ran, which stands for reaching a use case. */
function reachedUseCase(?bool $reached = null): bool
{
    static $value = false;

    if ($reached !== null) {
        $value = $reached;
    }

    return $value;
}

/**
 * Answer the business that BusinessContext gives and the one pinned in the transaction.
 *
 * @return array{tienda: string, fijada: string}
 */
function resolvedBusiness(BusinessContext $context): array
{
    reachedUseCase(true);

    return [
        'tienda' => $context->current()->value,
        'fijada' => (string) DB::scalar("select current_setting('app.business_id', true)"),
    ];
}

beforeEach(function () {
    DemoAccounts::seed();
    reachedUseCase(false);

    // A throwaway panel route, outside the prefixes that the route scans walk.
    Route::prefix('api/v1/pruebas/panel')->middleware(['api', 'auth:sanctum', 'business:staff'])->group(function (): void {
        Route::match(['get', 'post'], 'tienda', fn (BusinessContext $context) => resolvedBusiness($context));
    });
});

it('resolves the business of every panel route from the session and never from the URL', function () {
    $violations = [];

    // An empty list would let the scan pass without checking any route.
    expect(routesUnder('api/v1/panel'))->not->toBeEmpty();

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

it('resolves the business of every route of the account from a resource of the customer and never from the URL', function () {
    $violations = [];

    foreach (routesUnder('api/v1/cuenta') as $route) {
        $namesAResource = array_intersect($route->parameterNames(), array_keys(CustomerBusinessContext::RESOURCES)) !== [];

        if ($namesAResource && ! in_array('business:account', $route->gatherMiddleware(), true)) {
            $violations[] = "{$route->uri()} lacks business:account";
        }

        if (array_intersect($route->parameterNames(), ['tienda', 'business', 'business_id']) !== []) {
            $violations[] = "{$route->uri()} takes the business from the URL";
        }
    }

    expect($violations)->toBe([]);
});

it('gives each person of the staff the business of the session and pins it in the transaction', function (string $email, string $businessId) {
    getJson('/api/v1/pruebas/panel/tienda', DemoAccounts::signIn($email))
        ->assertOk()
        ->assertExactJson(['tienda' => $businessId, 'fijada' => $businessId]);
})->with([
    'owner' => ['duena@archivumparfums.test', BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID],
    'co-owner' => ['coduena@archivumparfums.test', BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID],
    'advisor' => ['asesor@archivumparfums.test', BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID],
    'owner of the second business' => ['dueno@esenciasdelnorte.test', BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID],
]);

it('ignores another business sent in the body or in the query of a panel request', function () {
    $other = BusinessesSeeder::DEMO_ESENCIAS_DEL_NORTE_ID;

    postJson("/api/v1/pruebas/panel/tienda?business_id={$other}&tienda={$other}", ['business_id' => $other, 'tienda' => $other], DemoAccounts::signIn('duena@archivumparfums.test'))
        ->assertOk()
        ->assertJsonPath('tienda', BusinessesSeeder::DEMO_ARCHIVUMPARFUMS_ID);
});

it('answers 401 without a token and 403 to a customer, before reaching any use case', function () {
    getJson('/api/v1/pruebas/panel/tienda')->assertUnauthorized();

    getJson('/api/v1/pruebas/panel/tienda', DemoAccounts::signIn('mariana@clientes.test'))
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido');

    expect(reachedUseCase())->toBeFalse();
});
