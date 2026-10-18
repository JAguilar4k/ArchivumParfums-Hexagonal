<?php

declare(strict_types=1);

use Src\Accounts\Users\Infrastructure\Security\BcryptPasswordHasher;

it('checks the configured bcrypt hash without changing the password', function () {
    $passwords = new BcryptPasswordHasher;
    $hash = $passwords->hash(' clave-segura ');

    expect($passwords->check(' clave-segura ', $hash))->toBeTrue()
        ->and($passwords->check('clave-segura', $hash))->toBeFalse();
});

it('refuses incompatible passwords with or without a stored hash', function (string $password) {
    $passwords = new BcryptPasswordHasher;
    $hash = $passwords->hash('clave-segura');

    expect($passwords->check($password, $hash))->toBeFalse()
        ->and($passwords->check($password, null))->toBeFalse();
})->with([
    'embedded null byte' => "clave\0incompatible",
    'null byte after the correct password' => "clave-segura\0suffix",
    'long password' => str_repeat('x', 100),
]);
