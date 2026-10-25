<?php

declare(strict_types=1);

use Src\Scheduling\Shared\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Scheduling\StaffSchedules\Domain\Entities\StaffSchedule;
use Src\Scheduling\StaffSchedules\Domain\ValueObjects\StaffScheduleId;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

// Helper para no repetir el UUID del member
function createSchedule(): StaffSchedule
{
    return StaffSchedule::create(
        new StaffScheduleId('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'),
        new BusinessId('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71'),
        new MemberId('019a1f2e-7c3b-7d4e-9a12-2b3c4d5e6f72')
    );
}

test('permite agregar varios tramos contiguos en el mismo día para un miembro', function () {
    $horario = createSchedule();
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '12:00'));
    $horario->addSlot($lunes, TimeRange::create('12:00', '17:00'));

    expect($horario->getSlots()[$lunes->value])->toHaveCount(2);
});

test('rechaza agregar tramos que se traslapan en el mismo día para un miembro', function () {
    $horario = createSchedule();
    $lunes = Weekday::MONDAY;

    $horario->addSlot($lunes, TimeRange::create('08:00', '13:00'));

    // Cambiamos Exception::class por TimeSlotOverlapsException::class
    expect(fn () => $horario->addSlot($lunes, TimeRange::create('12:00', '15:00')))
        ->toThrow(TimeSlotOverlapsException::class, 'scheduling.time_slot_overlaps');
});

test('permite al miembro tener tramos en el mismo horario pero en días distintos', function () {
    $horario = createSchedule();

    $horario->addSlot(Weekday::MONDAY, TimeRange::create('08:00', '12:00'));
    $horario->addSlot(Weekday::TUESDAY, TimeRange::create('08:00', '12:00'));

    expect($horario->getSlots()[Weekday::MONDAY->value])->toHaveCount(1);
    expect($horario->getSlots()[Weekday::TUESDAY->value])->toHaveCount(1);
});