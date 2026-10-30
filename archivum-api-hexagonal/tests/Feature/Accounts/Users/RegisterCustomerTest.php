<?php

declare(strict_types=1);

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

pest()->use(RefreshDatabaseAsOwner::class);

/**
 * A valid sign-up of a fictitious customer, with some fields replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function signUp(array $overrides = []): array
{
    return [
        'nombre' => 'Mariana Rojas',
        'correo' => 'mariana@clientes.test',
        'contrasena' => 'clave-de-prueba',
        ...$overrides,
    ];
}

/**
 * Assert a 422 problem that points at a field.
 *
 * @param  TestResponse<JsonResponse>  $response
 */
function expectInvalidField(TestResponse $response, string $field): void
{
    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/datos-invalidos')
        ->assertJsonValidationErrors([$field], 'errores');
}

it('answers 201 with the location of /cuenta and the public data of a new customer', function () {
    $response = postJson('/api/v1/cuentas', signUp(['nombre' => '  Mariana Rojas  ', 'correo' => ' Mariana@Clientes.TEST ']))
        ->assertCreated()
        ->assertHeader('Location', route('accounts.current.show'));

    $user = UserModel::query()->sole();

    expect($response->json('data'))->toBe([
        'id' => $user->id,
        'nombre' => 'Mariana Rojas',
        'correo' => 'mariana@clientes.test',
        'rol' => 'cliente',
        'tienda' => null,
    ])->and(Hash::check('clave-de-prueba', $user->password))->toBeTrue();
});

it('lets the new customer sign in afterwards and is seen as a customer by GET /cuenta', function () {
    postJson('/api/v1/cuentas', signUp())->assertCreated();

    $token = postJson('/api/v1/sesiones', ['correo' => 'mariana@clientes.test', 'contrasena' => 'clave-de-prueba', 'dispositivo' => 'Celular'])
        ->assertCreated()
        ->json('data.token');

    getJson('/api/v1/cuenta', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.rol', 'cliente')
        ->assertJsonPath('data.tienda', null);
});

it('does not sign in and never exposes the password, its hash or a token', function () {
    $response = postJson('/api/v1/cuentas', signUp())->assertCreated();

    expect(array_keys($response->json('data')))->toBe(['id', 'nombre', 'correo', 'rol', 'tienda'])
        ->and($response->getContent())->not->toContain('clave-de-prueba')
        ->and($response->getContent())->not->toContain((string) UserModel::query()->sole()->password)
        ->and($response->getContent())->not->toContain('token');

    assertDatabaseCount('personal_access_tokens', 0);
});

it('ignores a role, a business_id or a business in the body and creates no member nor customer row', function () {
    $business = BusinessModel::factory()->create();

    postJson('/api/v1/cuentas', signUp([
        'rol' => 'dueno',
        'role' => 'owner',
        'business_id' => $business->id,
        'tienda' => $business->slug,
    ]))->assertCreated()
        ->assertJsonPath('data.rol', 'cliente')
        ->assertJsonPath('data.tienda', null);

    assertDatabaseCount('members', 0);
    assertDatabaseCount('customers', 0);
});

it('answers 422 pointing at correo for an email already registered, whatever its case, and keeps the first account', function () {
    postJson('/api/v1/cuentas', signUp())->assertCreated();

    $response = postJson('/api/v1/cuentas', signUp(['nombre' => 'Otra persona', 'correo' => 'MARIANA@Clientes.test', 'contrasena' => 'otra-clave-segura']));

    expectInvalidField($response, 'correo');
    expect($response->json('errores.correo'))->toBe([__('accounts.email_already_registered')]);
    assertDatabaseCount('users', 1);
    assertDatabaseHas('users', ['email' => 'mariana@clientes.test', 'name' => 'Mariana Rojas']);
});

it('answers 422 with an error per field for missing, malformed or weak data, and stores nothing', function (string $field, mixed $value) {
    expectInvalidField(postJson('/api/v1/cuentas', signUp([$field => $value])), $field);

    assertDatabaseCount('users', 0);
})->with([
    'missing name' => ['nombre', null],
    'blank name' => ['nombre', '   '],
    'name too long' => ['nombre', str_repeat('a', 121)],
    'missing email' => ['correo', null],
    'malformed email' => ['correo', 'mariana-sin-arroba'],
    'email too long' => ['correo', str_repeat('a', 250).'@clientes.test'],
    'missing password' => ['contrasena', null],
    'password shorter than 8' => ['contrasena', '1234567'],
]);

it('accepts a password of exactly 8 characters', function () {
    postJson('/api/v1/cuentas', signUp(['contrasena' => '12345678']))->assertCreated();
});

it('reports every missing field of an empty body', function () {
    postJson('/api/v1/cuentas', [])->assertUnprocessable()
        ->assertJsonValidationErrors(['nombre', 'correo', 'contrasena'], 'errores');
});

it('answers 422 and never 500 to the loser of two simultaneous sign-ups with the same email', function () {
    $signUp = function (string $name): Closure {
        return function () use ($name): array {
            $request = Request::create('/api/v1/cuentas', 'POST', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], content: (string) json_encode([
                'nombre' => $name,
                'correo' => 'simultaneo@clientes.test',
                'contrasena' => 'clave-de-prueba',
            ]));
            $response = app(Kernel::class)->handle($request);

            return [$response->getStatusCode(), array_keys((array) (json_decode((string) $response->getContent(), true)['errores'] ?? []))];
        };
    };

    $results = inParallel(['users'], $signUp('Primera'), $signUp('Segunda'));

    expect(array_column($results, 0))->toEqualCanonicalizing([201, 422])
        ->and(array_values(array_filter(array_column($results, 1))))->toBe([['correo']]);
});
