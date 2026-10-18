<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

const SESSION_PASSWORD = 'clave-de-demostracion';

beforeEach(function () {
    config(['archivum.demo_password' => SESSION_PASSWORD]);
    seed();
});

it('answers a bearer token for the owner, the advisor and a customer of the seeders', function (string $email) {
    $response = postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => SESSION_PASSWORD])
        ->assertCreated()
        ->assertJsonPath('data.tipo', 'Bearer');

    $user = UserModel::query()->where('email', $email)->sole();

    expect(array_keys($response->json('data')))->toBe(['token', 'tipo'])
        ->and($response->json('data.token'))->toStartWith($user->tokens()->sole()->getKey().'|');
})->with([
    'owner' => 'duena@archivumparfums.test',
    'advisor' => 'asesor@archivumparfums.test',
    'customer' => 'mariana@clientes.test',
]);

it('finds the account whatever the case of the email', function () {
    postJson('/api/v1/sesiones', ['correo' => 'Duena@ArchivumParfums.TEST', 'contrasena' => SESSION_PASSWORD])
        ->assertCreated();
});

it('gives every sign-in its own token', function () {
    postJson('/api/v1/sesiones', ['correo' => 'mariana@clientes.test', 'contrasena' => SESSION_PASSWORD])->assertCreated();
    postJson('/api/v1/sesiones', ['correo' => 'mariana@clientes.test', 'contrasena' => SESSION_PASSWORD])->assertCreated();

    expect(UserModel::query()->where('email', 'mariana@clientes.test')->sole()->tokens()->count())->toBe(2);
});

it('answers an unknown email and a wrong password the same way, without telling which one failed', function () {
    $unknownEmail = postJson('/api/v1/sesiones', ['correo' => 'nadie@archivumparfums.test', 'contrasena' => SESSION_PASSWORD])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');
    $wrongPassword = postJson('/api/v1/sesiones', ['correo' => 'duena@archivumparfums.test', 'contrasena' => 'otra-clave'])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect($unknownEmail->json('detail'))->toBe('El correo o la contraseña no son correctos.')
        ->and(Arr::except($unknownEmail->json(), 'instance'))->toBe(Arr::except($wrongPassword->json(), 'instance'))
        ->and($unknownEmail->json('type'))->toEndWith('/problemas/datos-invalidos');
    assertDatabaseCount('personal_access_tokens', 0);
});

it('does not trim the password', function () {
    postJson('/api/v1/sesiones', ['correo' => 'duena@archivumparfums.test', 'contrasena' => ' '.SESSION_PASSWORD.' '])
        ->assertUnprocessable();
});

it('refuses incompatible passwords equally for existing and unknown accounts', function (string $password) {
    $existing = postJson('/api/v1/sesiones', ['correo' => 'duena@archivumparfums.test', 'contrasena' => $password])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');
    $unknown = postJson('/api/v1/sesiones', ['correo' => 'nadie@archivumparfums.test', 'contrasena' => $password])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json');

    expect(Arr::except($existing->json(), 'instance'))->toBe(Arr::except($unknown->json(), 'instance'))
        ->and($unknown->json('detail'))->toBe('El correo o la contraseña no son correctos.');
    assertDatabaseCount('personal_access_tokens', 0);
})->with([
    'embedded null byte' => "clave\0incompatible",
    'long password' => str_repeat('x', 100),
]);

it('refuses missing or malformed credentials with the errors of each field', function (array $payload, array $fields) {
    $response = postJson('/api/v1/sesiones', $payload)->assertUnprocessable();

    expect(array_keys($response->json('errores')))->toBe($fields);
})->with([
    'nothing' => [[], ['correo', 'contrasena']],
    'malformed email' => [['correo' => 'duena.archivumparfums.test', 'contrasena' => SESSION_PASSWORD], ['correo']],
]);
