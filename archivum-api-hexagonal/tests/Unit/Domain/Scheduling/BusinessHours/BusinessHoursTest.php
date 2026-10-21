<?php

declare(strict_types=1);

use Src\Scheduling\BusinessHours\Domain\BusinessHours;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;

test('permite agregar varios tramos contiguos en el mismo día', function () {
    $horario = BusinessHours::create('tienda-1');
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '12:00'));
    $horario->addSlot($lunes, TimeRange::create('12:00', '17:00'));

    expect($horario->getSlots()[$lunes->value])->toHaveCount(2);
});

test('rechaza agregar tramos que se traslapan en el mismo día', function () {
    $horario = BusinessHours::create('tienda-1');
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '13:00'));

    // Intenta agregar un tramo que choca con el anterior
    expect(fn () => $horario->addSlot($lunes, TimeRange::create('12:00', '15:00')))
        ->toThrow(Exception::class, 'scheduling.business_hours.overlapping_slots');
});

test('permite agregar tramos en el mismo horario pero en días distintos', function () {
    $horario = BusinessHours::create('tienda-1');

    $horario->addSlot(Weekday::MONDAY, TimeRange::create('08:00', '12:00'));
    $horario->addSlot(Weekday::TUESDAY, TimeRange::create('08:00', '12:00'));

    expect($horario->getSlots()[Weekday::MONDAY->value])->toHaveCount(1);
    expect($horario->getSlots()[Weekday::TUESDAY->value])->toHaveCount(1);
});
