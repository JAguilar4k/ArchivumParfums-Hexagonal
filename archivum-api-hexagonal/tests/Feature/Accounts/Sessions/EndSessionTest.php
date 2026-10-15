<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenRevoker;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    config(['archivum.demo_password' => 'clave-de-demostracion']);
    seed();
});

/**
 * Sign in as a demo account and return the bearer header of the new session.
 *
 * @return array<string, string>
 */
function openSession(string $email): array
{
    $token = postJson('/api/v1/sesiones', ['correo' => $email, 'contrasena' => 'clave-de-demostracion'])->json('data.token');

    return ['Authorization' => "Bearer {$token}"];
}

/** Forget the account the guard remembers, as a new request would. */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}

it('signs out and then refuses the same token with 401', function () {
    $session = openSession('mariana@clientes.test');

    deleteJson('/api/v1/sesiones/actual', [], $session)->assertNoContent();
    nextRequest();

    getJson('/api/v1/cuenta', $session)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-autenticado');
});

it('deletes the row of the token and keeps the other sessions of the account', function () {
    $closed = openSession('mariana@clientes.test');
    $open = openSession('mariana@clientes.test');
    $user = UserModel::query()->where('email', 'mariana@clientes.test')->sole();

    deleteJson('/api/v1/sesiones/actual', [], $closed)->assertNoContent();
    nextRequest();

    expect($user->tokens()->count())->toBe(1);
    getJson('/api/v1/cuenta', $open)->assertOk()->assertJsonPath('data.correo', 'mariana@clientes.test');
});

it('answers 401 without a token or with a revoked one', function () {
    deleteJson('/api/v1/sesiones/actual')->assertUnauthorized();

    $session = openSession('duena@archivumparfums.test');
    deleteJson('/api/v1/sesiones/actual', [], $session)->assertNoContent();
    nextRequest();

    deleteJson('/api/v1/sesiones/actual', [], $session)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('revokes every token of one account and no other', function () {
    openSession('mariana@clientes.test');
    openSession('mariana@clientes.test');
    openSession('carlos@clientes.test');
    $mariana = UserModel::query()->where('email', 'mariana@clientes.test')->sole();
    $carlos = UserModel::query()->where('email', 'carlos@clientes.test')->sole();

    (new SanctumTokenRevoker)->revokeAll(new UserId($mariana->id));

    expect($mariana->tokens()->count())->toBe(0)
        ->and($carlos->tokens()->count())->toBe(1);
});
