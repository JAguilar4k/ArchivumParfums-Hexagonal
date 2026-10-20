<?php

declare(strict_types=1);

use Src\Accounts\Sessions\Application\DTOs\StartSessionCommand;
use Src\Accounts\Sessions\Application\UseCases\StartSession;
use Src\Accounts\Sessions\Domain\Exceptions\InvalidCredentialsException;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Tests\Support\Accounts\FakePasswordHasher;
use Tests\Support\Accounts\FakeTokenIssuer;
use Tests\Support\Accounts\InMemoryUserRepository;

/**
 * Wire the use case to in-memory ports with one account, duena@archivum.test with the password clave-segura.
 *
 * @return array{StartSession, FakePasswordHasher, FakeTokenIssuer, User}
 */
function startSessionWiring(): array
{
    $users = new InMemoryUserRepository;
    $passwords = new FakePasswordHasher;
    $tokens = new FakeTokenIssuer;
    $user = User::create($users->nextId(), new UserName('Valeria Solano'), new Email('duena@archivum.test'), $passwords->hash('clave-segura'));
    $users->save($user);

    return [new StartSession($users, $passwords, $tokens), $passwords, $tokens, $user];
}

it('issues a token named after the device for the account of the credentials', function () {
    [$startSession, , $tokens, $user] = startSessionWiring();

    $session = $startSession->execute(new StartSessionCommand('duena@archivum.test', 'clave-segura', 'Computadora'));

    expect($session->plainTextToken)->toBe('token-1')
        ->and($tokens->issued)->toBe([[$user->id->value, 'Computadora']]);
});

it('finds the account whatever the case of the email', function () {
    [$startSession, , $tokens, $user] = startSessionWiring();

    $startSession->execute(new StartSessionCommand(' Duena@Archivum.TEST ', 'clave-segura', 'Celular'));

    expect($tokens->issued)->toBe([[$user->id->value, 'Celular']]);
});

it('refuses an unknown email and a wrong password with the same exception, after checking a password', function (string $email, string $password) {
    [$startSession, $passwords, $tokens] = startSessionWiring();

    expect(fn () => $startSession->execute(new StartSessionCommand($email, $password, 'Computadora')))
        ->toThrow(InvalidCredentialsException::class, 'accounts.invalid_credentials');

    expect($passwords->checks)->toBe(1)
        ->and($tokens->issued)->toBe([]);
})->with([
    'unknown email' => ['nadie@archivum.test', 'clave-segura'],
    'wrong password' => ['duena@archivum.test', 'otra-clave'],
]);
