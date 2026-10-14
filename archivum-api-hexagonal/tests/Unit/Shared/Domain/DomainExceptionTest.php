<?php

declare(strict_types=1);

use Src\Shared\Domain\Exceptions\DomainException;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\Exceptions\NotFoundException;

it('carries the translation key and its parameters', function () {
    $exception = new InvalidValueException('shared.invalid_identifier', ['valor' => 'abc']);

    expect($exception)->toBeInstanceOf(DomainException::class)
        ->and($exception->translationKey())->toBe('shared.invalid_identifier')
        ->and($exception->parameters())->toBe(['valor' => 'abc'])
        ->and($exception->getMessage())->toBe('shared.invalid_identifier');
});

it('has no parameters unless they are given', function () {
    $exception = new class('catalog.perfume_not_found') extends NotFoundException {};

    expect($exception->parameters())->toBe([]);
});
