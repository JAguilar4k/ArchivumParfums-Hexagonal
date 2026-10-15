<?php

declare(strict_types=1);

use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterAccount;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Accounts\FakePasswordHasher;
use Tests\Support\Accounts\InMemoryUserRepository;

/**
 * Wire the use case to in-memory ports.
 *
 * @return array{RegisterAccount, InMemoryUserRepository}
 */
function registerAccountWiring(): array
{
    $users = new InMemoryUserRepository;

    return [new RegisterAccount($users, new FakePasswordHasher), $users];
}

it('opens an account with a normalised email and a hashed password', function () {
    [$registerAccount, $users] = registerAccountWiring();

    $user = $registerAccount->execute(new RegisterAccountCommand(' Valeria Solano ', 'Duena@Archivum.test', 'clave-segura'));

    expect($user->name->value)->toBe('Valeria Solano')
        ->and($user->email->value)->toBe('duena@archivum.test')
        ->and($user->passwordHash)->toBe('hashed:clave-segura')
        ->and($users->count())->toBe(1);
});

it('refuses an email that already has an account', function () {
    [$registerAccount] = registerAccountWiring();
    $registerAccount->execute(new RegisterAccountCommand('Valeria', 'duena@archivum.test', 'clave-segura'));

    $registerAccount->execute(new RegisterAccountCommand('Otra', 'DUENA@archivum.test', 'clave-segura'));
})->throws(EmailAlreadyRegisteredException::class);

it('refuses a short password before storing anything', function () {
    [$registerAccount, $users] = registerAccountWiring();

    expect(fn () => $registerAccount->execute(new RegisterAccountCommand('Valeria', 'duena@archivum.test', 'corta')))
        ->toThrow(InvalidValueException::class, 'accounts.password_too_short');

    expect($users->count())->toBe(0);
});
