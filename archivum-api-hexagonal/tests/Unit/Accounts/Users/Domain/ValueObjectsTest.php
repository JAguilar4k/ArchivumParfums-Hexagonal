<?php

declare(strict_types=1);

use Src\Accounts\Users\Domain\ValueObjects\Email;
use Src\Accounts\Users\Domain\ValueObjects\PlainPassword;
use Src\Accounts\Users\Domain\ValueObjects\UserName;
use Src\Shared\Domain\Exceptions\InvalidValueException;

it('trims and lower-cases the email', function () {
    expect((new Email('  Duena@ArchivumParfums.TEST '))->value)->toBe('duena@archivumparfums.test');
});

it('rejects a malformed or oversized email', function (string $email) {
    new Email($email);
})->with([
    'without an at sign' => 'duena.archivum.test',
    'empty' => '',
    'too long' => str_repeat('a', 250).'@a.test',
])->throws(InvalidValueException::class, 'accounts.email_invalid');

it('keeps the password exactly as typed', function () {
    expect((new PlainPassword(' clave segura '))->value)->toBe(' clave segura ');
});

it('rejects a password shorter than eight characters', function () {
    new PlainPassword('1234567');
})->throws(InvalidValueException::class, 'accounts.password_too_short');

it('trims the name of the account', function () {
    expect((new UserName('  Valeria Solano  '))->value)->toBe('Valeria Solano');
});

it('accepts names supported by the original account schema without truncating them', function (string $name) {
    expect((new UserName($name))->value)->toBe($name);
})->with([
    'existing account with 150 characters' => str_repeat('a', 150),
    'database limit of 255 characters' => str_repeat('a', 255),
]);

it('rejects an empty or oversized name', function (string $name) {
    new UserName($name);
})->with(['empty' => '   ', 'too long' => str_repeat('a', 256)])
    ->throws(InvalidValueException::class, 'accounts.name_invalid');
