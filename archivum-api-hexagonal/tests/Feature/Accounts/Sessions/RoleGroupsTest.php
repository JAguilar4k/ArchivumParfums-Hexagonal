<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Src\Shared\Domain\Contracts\BusinessContext;
use Tests\Support\Businesses\TwoBusinesses;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\json;

pest()->use(RefreshDatabaseAsOwner::class);

const CO_OWNER = 'coduena@archivumparfums.test';

/**
 * Routes kept for the owners of the business, by method and URI; '*' is any method and a URI covers the routes
 * under it. Logo, bottles and suppliers join when they arrive.
 *
 * @return list<array{string, string}>
 */
function ownerOnlyRoutes(): array
{
    return [
        ['*', 'api/v1/panel/tienda'],
        ['*', 'api/v1/panel/servicios'],
        ['*', 'api/v1/panel/horario'],
        ['*', 'api/v1/panel/personal/{persona}/horario'],
        ['*', 'api/v1/panel/bloqueos'],
        ['*', 'api/v1/panel/perfumes'],
        ['*', 'api/v1/panel/botellas'],
        ['*', 'api/v1/panel/proveedores'],
        ['POST', 'api/v1/panel/citas'],
        ['POST', 'api/v1/panel/citas/{cita}/cancelacion'],
        ['PATCH', 'api/v1/panel/citas/{cita}'],
    ];
}

/**
 * Routes kept for customers, by method and URI. Every route under api/v1/cuenta/ joins them too.
 *
 * @return list<array{string, string}>
 */
function customerOnlyRoutes(): array
{
    return [
        ['POST', 'api/v1/tiendas/{tienda}/citas'],
        ['POST', 'api/v1/tiendas/{tienda}/pedidos'],
        ['POST', 'api/v1/tiendas/{tienda}/lista-de-espera'],
        ['POST', 'api/v1/tiendas/{tienda}/paquetes/{paquete}/compra'],
    ];
}

/**
 * Whether a route falls under one of the rules of a group.
 *
 * @param  list<array{string, string}>  $rules
 */
function routeFallsUnder(RouteDefinition $route, array $rules): bool
{
    foreach ($rules as [$method, $uri]) {
        $sameUri = $method === '*'
            ? $route->uri() === $uri || str_starts_with($route->uri(), "{$uri}/")
            : $route->uri() === $uri;

        if ($sameUri && ($method === '*' || in_array($method, $route->methods(), true))) {
            return true;
        }
    }

    return false;
}

/**
 * Endpoints of the owners only group: method, path and payload. Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function ownerOnlyEndpoints(): array
{
    return [
        'read the profile of the business' => ['GET', '/api/v1/panel/tienda', []],
        'change the profile of the business' => ['PATCH', '/api/v1/panel/tienda', ['nombre' => 'Otro nombre', 'ajustes' => ['margen_botella' => 9000]]],
    ];
}

/**
 * Endpoints of the customers only group: method, path and payload. Every issue that adds one adds its row.
 *
 * @return array<string, array{string, string, array<string, mixed>}>
 */
function customerOnlyEndpoints(): array
{
    return [
        'example: join a business as a customer (test route)' => ['POST', '/api/v1/tiendas/esencias-del-norte/pruebas/clientes', []],
    ];
}

/** Rows inserted, updated or deleted so far in the transaction of the test, besides the tokens Sanctum touches. */
function rowsWrittenSoFar(): int
{
    return (int) DB::scalar(
        "select coalesce(sum(n_tup_ins + n_tup_upd + n_tup_del), 0) from pg_stat_xact_user_tables
          where relname <> 'personal_access_tokens'",
    );
}

/**
 * Send a request as an account and expect a 403 problem that wrote nothing.
 *
 * @param  array<string, mixed>  $payload
 */
function expectForbiddenWithoutWrites(string $email, string $method, string $path, array $payload): void
{
    TwoBusinesses::actAs($email);
    $before = rowsWrittenSoFar();

    json($method, $path, $payload)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido');

    expect(rowsWrittenSoFar())->toBe($before, "{$method} {$path} wrote rows for {$email}.");
}

beforeEach(function () {
    TwoBusinesses::seed();

    // Example of the customers only group until its first endpoint exists.
    Route::post('api/v1/tiendas/{tienda}/pruebas/clientes', fn (BusinessContext $context, Request $request) => response()->json(['data' => DB::table('customers')->insertGetId([
        'business_id' => $context->current()->value,
        'user_id' => $request->user()?->getAuthIdentifier(),
        'created_at' => now(),
        'updated_at' => now(),
    ])], 201))->middleware(['api', 'auth:sanctum', 'business:route', 'role:customer']);
});

it('lets the owner and the co-owner through the owners only group', function (string $method, string $path, array $payload, string $email) {
    TwoBusinesses::actAs($email);

    expect(json($method, $path, $payload)->status())->not->toBeIn([401, 403]);
})->with(ownerOnlyEndpoints())->with([
    'owner' => TwoBusinesses::OWNERS[TwoBusinesses::FIRST],
    'co-owner' => CO_OWNER,
]);

it('forbids the advisor and the customer in the owners only group, also the reads, and writes nothing', function (string $method, string $path, array $payload, string $email) {
    expectForbiddenWithoutWrites($email, $method, $path, $payload);
})->with(ownerOnlyEndpoints())->with([
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('lets a customer through the customers only group', function (string $method, string $path, array $payload) {
    TwoBusinesses::actAs(TwoBusinesses::CUSTOMER);

    expect(json($method, $path, $payload)->status())->not->toBeIn([401, 403]);
})->with(customerOnlyEndpoints());

it('forbids every account with a row in members in the customers only group and writes nothing', function (string $method, string $path, array $payload, string $email) {
    expectForbiddenWithoutWrites($email, $method, $path, $payload);
})->with(customerOnlyEndpoints())->with([
    'owner' => TwoBusinesses::OWNERS[TwoBusinesses::FIRST],
    'co-owner' => CO_OWNER,
    'advisor' => TwoBusinesses::ADVISOR,
    'owner of the other business' => TwoBusinesses::OWNERS[TwoBusinesses::SECOND],
]);

it('asks for a token in both groups', function (string $method, string $path, array $payload) {
    json($method, $path, $payload)->assertUnauthorized();
})->with([...ownerOnlyEndpoints(), ...customerOnlyEndpoints()]);

it('keeps GET /cuenta open to the three roles', function (string $email) {
    TwoBusinesses::actAs($email);

    getJson('/api/v1/cuenta')->assertOk();
})->with([
    'owner' => TwoBusinesses::OWNERS[TwoBusinesses::FIRST],
    'advisor' => TwoBusinesses::ADVISOR,
    'customer' => TwoBusinesses::CUSTOMER,
]);

it('puts every registered route of the owners only group behind role:owner', function () {
    $violations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (routeFallsUnder($route, ownerOnlyRoutes()) && ! in_array('role:owner', $route->gatherMiddleware(), true)) {
            $violations[] = implode('|', $route->methods())." {$route->uri()} lacks role:owner";
        }
    }

    expect($violations)->toBe([]);
});

it('puts every registered route of the customers only group behind role:customer', function () {
    $violations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $underAccount = str_starts_with($route->uri(), 'api/v1/cuenta/');

        if (($underAccount || routeFallsUnder($route, customerOnlyRoutes())) && ! in_array('role:customer', $route->gatherMiddleware(), true)) {
            $violations[] = implode('|', $route->methods())." {$route->uri()} lacks role:customer";
        }
    }

    expect($violations)->toBe([]);
});
