<?php

declare(strict_types=1);

use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Shared\Domain\Exceptions\InvalidValueException;

test('rechaza un tramo con el fin igual al inicio', function () {
    expect(fn () => TimeRange::create('10:00', '10:00'))
        ->toThrow(InvalidValueException::class, 'scheduling.time_range_invalid_order');
});

test('rechaza un tramo con el fin anterior al inicio', function () {
    expect(fn () => TimeRange::create('10:00', '09:00'))
        ->toThrow(InvalidValueException::class, 'scheduling.time_range_invalid_order');
});


test('acepta dos tramos contiguos sin considerarlos traslape', function () {
    $tramo1 = TimeRange::create('17:00', '18:00');
    $tramo2 = TimeRange::create('18:00', '19:00');

    expect($tramo1->overlaps($tramo2))->toBeFalse();
    expect($tramo2->overlaps($tramo1))->toBeFalse();
});

test('rechaza dos tramos que se traslapan parcialmente', function () {
    $tramo1 = TimeRange::create('17:00', '18:30');
    $tramo2 = TimeRange::create('18:00', '19:00');

    expect($tramo1->overlaps($tramo2))->toBeTrue();
    expect($tramo2->overlaps($tramo1))->toBeTrue();
});
