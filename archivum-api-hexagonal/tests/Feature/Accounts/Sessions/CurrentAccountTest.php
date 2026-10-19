<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Businesses\Profile\Infrastructure\Persistence\Models\BusinessModel;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
    seed();
});

/**
 * Sign in as a demo account and return its bearer header.
 *
 * @return array<string, string>
 */
function signInAs(string $email): array
{
    $token = postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => 'clave-de-demostracion'])->json('data.token');

    return ['Authorization' => "Bearer {$token}"];
}

it('describes each person of the staff with the role and the business', function (string $email, string $name, string $role) {
    $business = BusinessModel::query()->where('slug', 'archivumparfums')->sole();

    getJson('/api/v1/cuenta', signInAs($email))
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => UserModel::query()->where('email', $email)->sole()->id,
            'nombre' => $name,
            'correo' => $email,
            'rol' => $role,
            'tienda' => ['id' => $business->id, 'nombre' => 'ArchivumParfums', 'slug' => 'archivumparfums'],
        ]]);
})->with([
    'owner' => ['duena@archivumparfums.test', 'Valeria Solano Ruiz', 'dueno'],
    'co-owner' => ['coduena@archivumparfums.test', 'Daniela Mora Vargas', 'dueno'],
    'advisor' => ['asesor@archivumparfums.test', 'Andrés Quesada Rojas', 'colaborador'],
]);

it('gives the owner of the second business that business', function () {
    getJson('/api/v1/cuenta', signInAs('dueno@esenciasdelnorte.test'))
        ->assertOk()
        ->assertJsonPath('data.rol', 'dueno')
        ->assertJsonPath('data.tienda.slug', 'esencias-del-norte');
});

it('describes a customer without a business', function () {
    getJson('/api/v1/cuenta', signInAs('sofia@clientes.test'))
        ->assertOk()
        ->assertJsonPath('data.rol', 'cliente')
        ->assertJsonPath('data.tienda', null);
});

it('never shows the password, its hash or the tokens', function () {
    $headers = signInAs('mariana@clientes.test');
    signInAs('mariana@clientes.test');

    $body = getJson('/api/v1/cuenta', $headers)->assertOk()->content();
    $user = UserModel::query()->where('email', 'mariana@clientes.test')->sole();

    expect($body)->not->toContain($user->password)
        ->and($body)->not->toContain('contrasena')
        ->and($body)->not->toContain('token');
    foreach ($user->tokens()->pluck('token') as $hash) {
        expect($body)->not->toContain($hash);
    }
});

it('answers 401 in RFC 9457 format without a token or with an invalid one', function (array $headers) {
    getJson('/api/v1/cuenta', $headers)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 401)
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-autenticado');
})->with([
    'no token' => [[]],
    'invalid token' => [['Authorization' => 'Bearer 999|no-es-un-token']],
    'malformed token' => [['Authorization' => 'Bearer basura']],
]);
