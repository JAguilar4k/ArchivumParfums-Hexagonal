<?php

declare(strict_types=1);

use Src\Accounts\Users\Application\DTOs\RegisterAccountCommand;
use Src\Accounts\Users\Application\UseCases\RegisterCustomer;
use Src\Accounts\Users\Domain\Exceptions\EmailAlreadyRegisteredException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Tests\Support\Accounts\FakePasswordHasher;
use Tests\Support\Accounts\InMemoryUserRepository;

/**
 * Wire the use case to in-memory ports.
 *
 * @return array{RegisterCustomer, InMemoryUserRepository}
 */
function registerCustomerWiring(): array
{
    $users = new InMemoryUserRepository;

    return [new RegisterCustomer($users, new FakePasswordHasher), $users];
}

it('opens the account of a customer with a normalised email and a hashed password', function () {
    [$registerCustomer, $users] = registerCustomerWiring();

    $user = $registerCustomer->execute(new RegisterAccountCommand(' Mariana Rojas ', 'Mariana@Clientes.TEST', 'clave-segura'));

    expect($user->name->value)->toBe('Mariana Rojas')
        ->and($user->email->value)->toBe('mariana@clientes.test')
        ->and($user->passwordHash)->toBe('hashed:clave-segura')
        ->and($users->count())->toBe(1);
});

it('refuses an email that already has an account, whatever its case', function () {
    [$registerCustomer] = registerCustomerWiring();
    $registerCustomer->execute(new RegisterAccountCommand('Mariana', 'mariana@clientes.test', 'clave-segura'));

    $registerCustomer->execute(new RegisterAccountCommand('Otra persona', 'MARIANA@clientes.test', 'otra-clave-segura'));
})->throws(EmailAlreadyRegisteredException::class);

it('refuses a password shorter than the policy and stores nothing', function () {
    [$registerCustomer, $users] = registerCustomerWiring();

    expect(fn () => $registerCustomer->execute(new RegisterAccountCommand('Mariana', 'mariana@clientes.test', 'corta')))
        ->toThrow(InvalidValueException::class, 'accounts.password_too_short');

    expect($users->count())->toBe(0);
});
