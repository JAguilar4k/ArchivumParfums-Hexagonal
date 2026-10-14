<?php

declare(strict_types=1);

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Identifier;

it('accepts a canonical uuid version 7', function () {
    $id = new BusinessId('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f');

    expect($id->value)->toBe('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f');
});

it('rejects values that are not a lowercase uuid version 7', function (string $value) {
    expect(fn () => new BusinessId($value))->toThrow(InvalidValueException::class, 'shared.invalid_identifier');
})->with([
    'empty' => '',
    'plain text' => 'archivum',
    'version 4' => '0199c1f2-8a3b-4c4d-9e5f-1a2b3c4d5e6f',
    'uppercase' => '0199C1F2-8A3B-7C4D-9E5F-1A2B3C4D5E6F',
    'wrong variant' => '0199c1f2-8a3b-7c4d-7e5f-1a2b3c4d5e6f',
    'surrounding spaces' => ' 0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f ',
]);

it('treats identifiers of the same type and value as equal', function () {
    $id = new BusinessId('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f');

    expect($id->equals(new BusinessId('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f')))->toBeTrue()
        ->and($id->equals(new BusinessId('0199c1f2-8a3b-7c4d-9e5f-000000000000')))->toBeFalse();
});

it('never treats identifiers of different types as equal', function () {
    $other = new readonly class('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f') extends Identifier {};

    expect(new BusinessId('0199c1f2-8a3b-7c4d-9e5f-1a2b3c4d5e6f')->equals($other))->toBeFalse();
});
