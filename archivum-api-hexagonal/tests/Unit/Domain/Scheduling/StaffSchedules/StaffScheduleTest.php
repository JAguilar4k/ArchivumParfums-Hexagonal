<?php

declare(strict_types=1);

use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Scheduling\StaffSchedules\Domain\StaffSchedule;

test('permite agregar varios tramos contiguos en el mismo día para un miembro', function () {
    $horario = StaffSchedule::create('member-123');
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '12:00'));
    $horario->addSlot($lunes, TimeRange::create('12:00', '17:00'));

    expect($horario->getSlots()[$lunes->value])->toHaveCount(2);
});

test('rechaza agregar tramos que se traslapan en el mismo día para un miembro', function () {
    $horario = StaffSchedule::create('member-123');
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '13:00'));

    expect(fn () => $horario->addSlot($lunes, TimeRange::create('12:00', '15:00')))
        ->toThrow(Exception::class, 'scheduling.staff_schedule.overlapping_slots');
});

test('permite al miembro tener tramos en el mismo horario pero en días distintos', function () {
    $horario = StaffSchedule::create('member-123');

    $horario->addSlot(Weekday::MONDAY, TimeRange::create('08:00', '12:00'));
    $horario->addSlot(Weekday::TUESDAY, TimeRange::create('08:00', '12:00'));

    expect($horario->getSlots()[Weekday::MONDAY->value])->toHaveCount(1);
    expect($horario->getSlots()[Weekday::TUESDAY->value])->toHaveCount(1);
});
