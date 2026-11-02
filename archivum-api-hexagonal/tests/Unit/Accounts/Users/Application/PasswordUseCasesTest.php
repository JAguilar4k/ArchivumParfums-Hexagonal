<?php

declare(strict_types=1);

use Src\Accounts\Users\Application\DTOs\ChangePasswordCommand;
use Src\Accounts\Users\Application\DTOs\ResetPasswordCommand;
use Src\Accounts\Users\Application\UseCases\ChangePassword;
use Src\Accounts\Users\Application\UseCases\RequestPasswordReset;
use Src\Accounts\Users\Application\UseCases\ResetPassword;
use Src\Accounts\Users\Domain\Entities\User;
use Src\Accounts\Users\Domain\Exceptions\InvalidCurrentPasswordException;
use Src\Accounts\Users\Domain\Exceptions\InvalidResetTokenException;
use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\ValueObjects\UserId;
use Tests\Support\Accounts\FakePasswordHasher;
use Tests\Support\Accounts\FakeTokenRevoker;
use Tests\Support\Accounts\InMemoryPasswordResetTokens;
use Tests\Support\Accounts\InMemoryUserRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

const PASSWORD_USER = '0192f0c4-0000-7000-8000-000000000031';
const PASSWORD_EMAIL = 'andrea@cuentas.test';

/** A repository with one account whose password is "clave-actual-1". */
function passwordAccounts(): InMemoryUserRepository
{
    $users = new InMemoryUserRepository;
    $users->save(User::create(new UserId(PASSWORD_USER), new UserName('Andrea'), new Email(PASSWORD_EMAIL), new FakePasswordHasher()->hash('clave-actual-1')));

    return $users;
}

it('changes the password and closes every session but the one of the request', function () {
    $users = passwordAccounts();
    $sessions = new FakeTokenRevoker;

    new ChangePassword($users, new FakePasswordHasher, $sessions, new RecordingTransactionRunner)
        ->execute(new ChangePasswordCommand(PASSWORD_USER, '7', 'clave-actual-1', 'clave-nueva-22'));

    expect($users->find(new UserId(PASSWORD_USER))?->passwordHash)->toBe(new FakePasswordHasher()->hash('clave-nueva-22'))
        ->and($sessions->revokedAccounts)->toBe([PASSWORD_USER])
        ->and($sessions->kept)->toBe(['7']);
});

it('changes nothing when the current password is wrong', function () {
    $users = passwordAccounts();
    $sessions = new FakeTokenRevoker;
    $change = new ChangePassword($users, new FakePasswordHasher, $sessions, new RecordingTransactionRunner);

    expect(fn () => $change->execute(new ChangePasswordCommand(PASSWORD_USER, '7', 'otra-clave-33', 'clave-nueva-22')))
        ->toThrow(InvalidCurrentPasswordException::class)
        ->and($users->find(new UserId(PASSWORD_USER))?->passwordHash)->toBe(new FakePasswordHasher()->hash('clave-actual-1'))
        ->and($sessions->revokedAccounts)->toBe([]);
});

it('sends one link to an email with an account', function () {
    $tokens = new InMemoryPasswordResetTokens;

    new RequestPasswordReset(passwordAccounts(), $tokens, $tokens)->execute(' ANDREA@cuentas.test ');

    expect($tokens->sent)->toBe([[PASSWORD_EMAIL, 'token-'.PASSWORD_USER]]);
});

it('sends nothing to an email without an account', function () {
    $tokens = new InMemoryPasswordResetTokens;

    new RequestPasswordReset(passwordAccounts(), $tokens, $tokens)->execute('nadie@cuentas.test');

    expect($tokens->sent)->toBe([])->and($tokens->tokens)->toBe([]);
});

it('sends nothing when the account got a link a moment ago', function () {
    $tokens = new InMemoryPasswordResetTokens(recentlyIssued: true);

    new RequestPasswordReset(passwordAccounts(), $tokens, $tokens)->execute(PASSWORD_EMAIL);

    expect($tokens->sent)->toBe([]);
});

it('sets the password, spends the token and closes every session', function () {
    $users = passwordAccounts();
    $tokens = new InMemoryPasswordResetTokens;
    $sessions = new FakeTokenRevoker;
    $token = (string) $tokens->issue(new UserId(PASSWORD_USER));

    new ResetPassword($users, $tokens, new FakePasswordHasher, $sessions, new RecordingTransactionRunner)
        ->execute(new ResetPasswordCommand(PASSWORD_EMAIL, $token, 'clave-nueva-22'));

    expect($users->find(new UserId(PASSWORD_USER))?->passwordHash)->toBe(new FakePasswordHasher()->hash('clave-nueva-22'))
        ->and($tokens->tokens)->toBe([])
        ->and($sessions->revokedAccounts)->toBe([PASSWORD_USER])
        ->and($sessions->kept)->toBe([]);
});

it('refuses a wrong token and an email without an account in the same way', function (string $email, string $token) {
    $users = passwordAccounts();
    $tokens = new InMemoryPasswordResetTokens;
    $tokens->issue(new UserId(PASSWORD_USER));
    $reset = new ResetPassword($users, $tokens, new FakePasswordHasher, new FakeTokenRevoker, new RecordingTransactionRunner);

    expect(fn () => $reset->execute(new ResetPasswordCommand($email, $token, 'clave-nueva-22')))
        ->toThrow(InvalidResetTokenException::class)
        ->and($users->find(new UserId(PASSWORD_USER))?->passwordHash)->toBe(new FakePasswordHasher()->hash('clave-actual-1'));
})->with([
    'a wrong token' => [PASSWORD_EMAIL, 'otro-token'],
    'an email without an account' => ['nadie@cuentas.test', 'token-'.PASSWORD_USER],
]);
