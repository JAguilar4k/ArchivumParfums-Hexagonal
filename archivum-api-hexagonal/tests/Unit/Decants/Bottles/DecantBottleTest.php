<?php

declare(strict_types=1);

use Src\Decants\Bottles\Domain\Entities\DecantBottle;
use Src\Decants\Bottles\Domain\Entities\DecantMovement;
use Src\Decants\Bottles\Domain\Exceptions\InsufficientMlException;
use Src\Decants\Bottles\Domain\ValueObjects\BottleStatus;
use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;
use Src\Decants\Bottles\Domain\ValueObjects\MovementType;
use Src\Decants\Shared\Domain\ValueObjects\DecantPrice;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

function domainBottle(int $remainingMl = 100): DecantBottle
{
    return DecantBottle::create(new DecantBottleId('0192f0c4-0000-7000-9000-000000000001'),
        new BusinessId('0192f0c4-0000-7000-9000-000000000002'), '0192f0c4-0000-7000-9000-000000000003',
        100, $remainingMl, new DateTimeImmutable('2026-10-24T10:00:00-06:00'), null);
}

it('R7: computes available ml from remaining minus reserved', function () {
    $bottle = domainBottle(20);
    $bottle->reserve(10, null, null, new DateTimeImmutable('2026-10-24'));

    expect($bottle->availableMl())->toBe(10);
    expect($bottle->remainingMl)->toBe(20);
    expect($bottle->reservedMl)->toBe(10);
    expect($bottle->status)->toBe(BottleStatus::Open);
});

it('R8: records the signed adjustment with its date reason and author', function () {
    $bottle = domainBottle(20);
    $at = new DateTimeImmutable('2026-10-24');

    $movement = $bottle->adjust(-5, 'Conteo físico', '0192f0c4-0000-7000-9000-000000000004', $at);

    expect($movement->type)->toBe(MovementType::Adjustment);
    expect($movement->ml)->toBe(-5);
    expect($movement->reason)->toBe('Conteo físico');
    expect($movement->occurredAt)->toBe($at);
    expect($bottle->remainingMl)->toBe(15);
});

it('R9: releases and consumes only previously reserved ml', function () {
    $bottle = domainBottle(10);
    $at = new DateTimeImmutable('2026-10-24');
    $bottle->reserve(10, null, null, $at);
    $bottle->release(5, null, null, $at);
    $bottle->consume(5, null, null, $at);

    expect($bottle->remainingMl)->toBe(5);
    expect($bottle->reservedMl)->toBe(0);
    expect(fn () => $bottle->reserve(6, null, null, $at))->toThrow(InsufficientMlException::class);
    expect(fn () => $bottle->consume(1, null, null, $at))->toThrow(InsufficientMlException::class);
    expect(fn () => $bottle->release(1, null, null, $at))->toThrow(InsufficientMlException::class);
});

it('R10: becomes empty when consumption uses the final ml and refuses later adjustments', function () {
    $bottle = domainBottle(5);
    $at = new DateTimeImmutable('2026-10-24');
    $bottle->reserve(5, null, null, $at);
    $bottle->consume(5, null, null, $at);

    expect($bottle->status)->toBe(BottleStatus::Empty);
    expect($bottle->remainingMl)->toBe(0);
    expect(fn () => $bottle->reserve(1, null, null, $at))->toThrow(InsufficientMlException::class);
    expect(fn () => $bottle->adjust(1, 'Reconteo', 'user', $at))->toThrow(InsufficientMlException::class);
});

it('refuses impossible initial remaining ml', function (int $remaining) {
    expect(fn () => domainBottle($remaining))->toThrow(InvalidValueException::class);
})->with([-1, 101]);

it('rejects an empty reason and zero or negative movement ml', function (MovementType $type, int $ml, ?string $reason) {
    expect(fn () => new DecantMovement($type, $ml, $reason, null, null, new DateTimeImmutable('2026-10-24')))->toThrow(InvalidValueException::class);
})->with([[MovementType::Adjustment, 1, '   '], [MovementType::Adjustment, 1, null], [MovementType::Adjustment, 0, 'Conteo'], [MovementType::Reservation, -1, null], [MovementType::Release, 0, null], [MovementType::Consumption, -1, null]]);

it('keeps adjustments within capacity and above reserved ml', function () {
    $bottle = domainBottle(10);
    $at = new DateTimeImmutable('2026-10-24');
    $bottle->reserve(5, null, null, $at);

    expect(fn () => $bottle->adjust(-6, 'Conteo', 'user', $at))->toThrow(InsufficientMlException::class);
    expect(fn () => $bottle->adjust(91, 'Conteo', 'user', $at))->toThrow(InsufficientMlException::class);
    expect($bottle->remainingMl)->toBe(10);
});

it('R3: rounds proportional prices upward with integers', function (int $price, int $capacity, int $ml, int $rounding, int $expected) {
    expect(DecantPrice::calculate($price, $capacity, $ml, $rounding))->toBe($expected);
})->with([[10000, 100, 10, 100, 1000], [85000, 75, 2, 100, 2300], [85000, 75, 2, 500, 2500], [0, 100, 10, 100, 0]]);
