<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Infrastructure\Security\SanctumTokenRevoker;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\Accounts\DemoAccounts;
use Tests\Support\RefreshDatabaseAsOwner;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;

pest()->use(RefreshDatabaseAsOwner::class);

beforeEach(function () {
    DemoAccounts::seed();
});

/** Forget the account the guard remembers, as a new request would. */
function nextRequest(): void
{
    app('auth')->forgetGuards();
}

it('signs out and then refuses the same token with 401', function () {
    $session = DemoAccounts::signIn('mariana@clientes.test');

    deleteJson('/api/v1/sesiones/actual', [], $session)->assertNoContent();
    nextRequest();

    getJson('/api/v1/cuenta', $session)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('type', rtrim((string) config('app.url'), '/').'/problemas/no-autenticado');
});

it('deletes the row of the token and keeps the other sessions of the account', function () {
    $closed = DemoAccounts::signIn('mariana@clientes.test', 'Celular');
    $open = DemoAccounts::signIn('mariana@clientes.test', 'Computadora');
    $user = UserModel::query()->where('email', 'mariana@clientes.test')->sole();

    deleteJson('/api/v1/sesiones/actual', [], $closed)->assertNoContent();
    nextRequest();

    expect($user->tokens()->pluck('name')->all())->toBe(['Computadora']);
    getJson('/api/v1/cuenta', $open)->assertOk()->assertJsonPath('data.correo', 'mariana@clientes.test');
});

it('answers 401 without a token or with a revoked one', function () {
    deleteJson('/api/v1/sesiones/actual')->assertUnauthorized();

    $session = DemoAccounts::signIn('duena@archivumparfums.test');
    deleteJson('/api/v1/sesiones/actual', [], $session)->assertNoContent();
    nextRequest();

    deleteJson('/api/v1/sesiones/actual', [], $session)
        ->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json');
});

it('revokes every token of one account and no other', function () {
    DemoAccounts::signIn('mariana@clientes.test');
    DemoAccounts::signIn('mariana@clientes.test');
    DemoAccounts::signIn('carlos@clientes.test');
    $mariana = UserModel::query()->where('email', 'mariana@clientes.test')->sole();
    $carlos = UserModel::query()->where('email', 'carlos@clientes.test')->sole();

    (new SanctumTokenRevoker)->revokeAll(new UserId($mariana->id));

    expect($mariana->tokens()->count())->toBe(0)
        ->and($carlos->tokens()->count())->toBe(1);
});
