<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenIssuer;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\json;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

const ABILITIES_PASSWORD = 'clave-de-demostracion';

/** Demo accounts of the seeders, one per role. */
const ABILITIES_ACCOUNTS = [
    'owner' => 'duena@archivumparfums.test',
    'staff' => 'asesor@archivumparfums.test',
    'customer' => 'mariana@clientes.test',
];

beforeEach(function () {
    config(['archivum.demo_password' => ABILITIES_PASSWORD]);
    seed();
});

/** Sign in through POST /api/v1/sesiones and answer the plain text token it gives. */
function signInForAbilities(string $email): string
{
    return (string) postJson('/api/v1/sesiones', [
        'correo' => $email,
        'contrasena' => ABILITIES_PASSWORD,
        'dispositivo' => 'Computadora',
    ])->assertCreated()->json('data.token');
}

/**
 * Send a request with a bearer token, forgetting the account of any earlier request.
 *
 * @return TestResponse<JsonResponse>
 */
function requestWithToken(string $token, string $method, string $path): TestResponse
{
    Auth::forgetGuards();

    return json($method, $path, [], ['Authorization' => "Bearer {$token}"]);
}

/**
 * Abilities a route declares with the abilities or ability middleware of Sanctum.
 *
 * @return list<string>
 */
function abilitiesOfRoute(RouteDefinition $route): array
{
    $abilities = [];

    foreach ($route->gatherMiddleware() as $middleware) {
        if (is_string($middleware) && preg_match('/^abilities?:(.+)$/', $middleware, $matches) === 1) {
            $abilities = [...$abilities, ...explode(',', $matches[1])];
        }
    }

    return $abilities;
}

it('issues from POST /sesiones a token with the abilities of the role of the account', function (string $role, AccountRole $accountRole) {
    signInForAbilities(ABILITIES_ACCOUNTS[$role]);

    $token = UserModel::query()->where('email', ABILITIES_ACCOUNTS[$role])->sole()->tokens()->sole();

    expect($token->abilities)->toEqualCanonicalizing(Ability::namesGrantedTo($accountRole));
})->with([
    'owner' => ['owner', AccountRole::Owner],
    'staff' => ['staff', AccountRole::Staff],
    'customer' => ['customer', AccountRole::Customer],
]);

it('stores the abilities given to the Sanctum adapter in the token', function () {
    $user = UserModel::factory()->create();

    new SanctumTokenIssuer()->issue(new UserId($user->id), 'Celular', ['appointments:view-own', 'appointments:attendance']);

    expect($user->tokens()->sole()->abilities)->toBe(['appointments:view-own', 'appointments:attendance']);
});

it('answers a 403 problem to the token of each role on a route that is not its own', function (string $role, string $method, string $path) {
    $token = signInForAbilities(ABILITIES_ACCOUNTS[$role]);

    requestWithToken($token, $method, $path)
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/prohibido')
        ->assertJsonPath('status', 403)
        ->assertJsonPath('title', __('problems.prohibido.title'));
})->with([
    'staff on the profile of the business' => ['staff', 'GET', '/api/v1/panel/tienda'],
    'staff on the catalog' => ['staff', 'GET', '/api/v1/panel/perfumes'],
    'staff on the bottles' => ['staff', 'GET', '/api/v1/panel/botellas'],
    'staff on the orders of the business' => ['staff', 'GET', '/api/v1/panel/pedidos'],
    'staff on the orders of a customer' => ['staff', 'GET', '/api/v1/cuenta/pedidos'],
    'customer on the profile of the business' => ['customer', 'PATCH', '/api/v1/panel/tienda'],
    'customer on the catalog' => ['customer', 'POST', '/api/v1/panel/perfumes'],
    'customer on the orders of the business' => ['customer', 'GET', '/api/v1/panel/pedidos'],
    'owner on the orders of a customer' => ['owner', 'GET', '/api/v1/cuenta/pedidos'],
    'owner placing an order as a customer' => ['owner', 'POST', '/api/v1/tiendas/archivumparfums/pedidos'],
]);

it('lets the token of each role through a route of its own', function (string $role, string $path) {
    $token = signInForAbilities(ABILITIES_ACCOUNTS[$role]);

    requestWithToken($token, 'GET', $path)->assertOk();
})->with([
    'owner on the profile of the business' => ['owner', '/api/v1/panel/tienda'],
    'owner on the orders of the business' => ['owner', '/api/v1/panel/pedidos'],
    'customer on its orders' => ['customer', '/api/v1/cuenta/pedidos'],
]);

it('serves GET /cuenta and DELETE /sesiones/actual to the three roles', function (string $role) {
    $token = signInForAbilities(ABILITIES_ACCOUNTS[$role]);

    requestWithToken($token, 'GET', '/api/v1/cuenta')->assertOk();
    requestWithToken($token, 'DELETE', '/api/v1/sesiones/actual')->assertNoContent();
})->with(array_keys(ABILITIES_ACCOUNTS));

it('declares an ability on every route of the panel, of /cuenta/... and of the authenticated operations of a business', function () {
    $violations = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $uri = $route->uri();
        $authenticated = in_array('auth:sanctum', $route->gatherMiddleware(), true);
        $mustDeclare = str_starts_with($uri, 'api/v1/panel/') || str_starts_with($uri, 'api/v1/cuenta/')
            || (str_starts_with($uri, 'api/v1/tiendas/') && $authenticated);

        if ($mustDeclare && abilitiesOfRoute($route) === []) {
            $violations[] = implode('|', $route->methods())." {$uri} declares no ability";
        }
    }

    expect($violations)->toBe([]);
});

it('declares only abilities that exist', function () {
    $known = array_map(fn (Ability $ability): string => $ability->value, Ability::cases());
    $unknown = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        foreach (array_diff(abilitiesOfRoute($route), $known) as $ability) {
            $unknown[] = "{$route->uri()} declares {$ability}";
        }
    }

    expect($unknown)->toBe([]);
});

it('asks no ability on GET /cuenta, DELETE /sesiones/actual and the public routes', function () {
    $declaring = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $open = in_array($route->uri(), ['api/v1/cuenta', 'api/v1/sesiones/actual'], true)
            || ! in_array('auth:sanctum', $route->gatherMiddleware(), true);

        if ($open && abilitiesOfRoute($route) !== []) {
            $declaring[] = implode('|', $route->methods())." {$route->uri()}";
        }
    }

    expect($declaring)->toBe([]);
});
