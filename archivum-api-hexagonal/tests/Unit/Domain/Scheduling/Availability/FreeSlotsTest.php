<?php

declare(strict_types=1);

use Src\Scheduling\Availability\Domain\FreeSlots;
use Src\Scheduling\Availability\Domain\ValueObjects\FreeSlot;
use Src\Scheduling\Availability\Domain\ValueObjects\MinuteRange;
use Src\Scheduling\Availability\Domain\ValueObjects\PersonDay;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/** A range of the day, given in HH:MM, as the reader turns a tramo of the schedule into minutes. */
function availabilityRange(string $from, string $to): MinuteRange
{
    return new MinuteRange(availabilityMinute($from), availabilityMinute($to));
}

/** Minutes counted from the midnight of a HH:MM time. */
function availabilityMinute(string $time): int
{
    [$hours, $minutes] = array_map(intval(...), explode(':', $time));

    return ($hours * 60) + $minutes;
}

/**
 * The start of each slot, as the HH:MM the tests read.
 *
 * @param  list<FreeSlot>  $slots
 * @return list<string>
 */
function availabilityStarts(array $slots): array
{
    return array_map(fn (FreeSlot $slot): string => sprintf(
        '%02d:%02d',
        intdiv($slot->range->start, 60),
        $slot->range->start % 60,
    ), $slots);
}

test('starts a slot every quarter of the clock while the appointment fits in the tramo', function (): void {
    $slots = FreeSlots::of(
        [availabilityRange('17:00', '20:00')],
        [new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('17:00', '20:00')], [], [])],
        45,
        0,
        0,
    );

    expect(availabilityStarts($slots))->toBe(['17:00', '17:15', '17:30', '17:45', '18:00', '18:15', '18:30', '18:45', '19:00', '19:15'])
        ->and($slots[0]->range->length())->toBe(45);
});

test('keeps only the minutes where the store and the person are both open', function (): void {
    $slots = FreeSlots::of(
        [availabilityRange('08:00', '12:00'), availabilityRange('14:00', '17:00')],
        [new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('09:00', '15:00')], [], [])],
        60,
        0,
        0,
    );

    expect(availabilityStarts($slots))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '14:00']);
});

test('aligns the first slot to the quarter of the clock after a tramo that opens in the middle of it', function (): void {
    $slots = FreeSlots::of(
        [availabilityRange('17:10', '18:00')],
        [new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('17:10', '18:00')], [], [])],
        30,
        0,
        0,
    );

    expect(availabilityStarts($slots))->toBe(['17:15', '17:30']);
});

test('takes out the slots each block of the person or of the whole store crosses', function (): void {
    $person = new PersonDay(
        '019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71',
        'Ana',
        [availabilityRange('09:00', '12:00')],
        [availabilityRange('10:00', '10:30')],
        [],
    );

    $slots = FreeSlots::of([availabilityRange('09:00', '12:00')], [$person], 30, 0, 0);

    // 09:30 ends exactly when the block starts, and the block is half-open, so it is still free.
    expect(availabilityStarts($slots))->toBe(['09:00', '09:15', '09:30', '10:30', '10:45', '11:00', '11:15', '11:30']);
});

test('reserves the margin of an active appointment even when the appointment itself does not cross the slot', function (): void {
    // An appointment from 10:00 that lasts 45 minutes and keeps 15 more blocked: [10:00, 11:00).
    $person = new PersonDay(
        '019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71',
        'Ana',
        [availabilityRange('09:00', '12:00')],
        [],
        [availabilityRange('10:00', '11:00')],
    );

    $slots = FreeSlots::of([availabilityRange('09:00', '12:00')], [$person], 45, 15, 0);

    // 09:15 would end at 10:00 and keep its margin until 10:15, which already crosses the appointment.
    expect(availabilityStarts($slots))->toBe(['09:00', '11:00', '11:15']);
});

test('leaves out the slots that start before the notice the store gives', function (): void {
    $person = new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('09:00', '12:00')], [], []);

    $slots = FreeSlots::of([availabilityRange('09:00', '12:00')], [$person], 30, 0, availabilityMinute('10:00'));

    expect(availabilityStarts($slots))->toBe(['10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30']);
});

test('lists in each slot only the people who can attend it, ordered by name', function (): void {
    $morning = new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('09:00', '11:00')], [], []);
    $afternoon = new PersonDay('019a1f2e-7c3b-7d4e-9a12-2b3c4d5e6f72', 'Beto', [availabilityRange('10:00', '12:00')], [], []);

    $slots = FreeSlots::of([availabilityRange('09:00', '12:00')], [$afternoon, $morning], 60, 0, 0);

    $names = array_map(fn (FreeSlot $slot): array => array_map(
        fn (PersonDay $person): string => $person->name,
        $slot->people,
    ), $slots);

    expect(availabilityStarts($slots))->toBe(['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00'])
        ->and($names[0])->toBe(['Ana'])
        ->and($names[4])->toBe(['Ana', 'Beto']);
});

test('offers nothing when the person has no tramo that day or the store is closed', function (): void {
    $withoutHours = new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [], [], []);
    $open = new PersonDay('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71', 'Ana', [availabilityRange('09:00', '12:00')], [], []);

    expect(FreeSlots::of([availabilityRange('09:00', '12:00')], [$withoutHours], 30, 0, 0))->toBe([])
        ->and(FreeSlots::of([], [$open], 30, 0, 0))->toBe([]);
});

test('refuses an empty range of the day and an inverted tramo of the schedule', function (): void {
    expect(fn () => new MinuteRange(600, 600))
        ->toThrow(InvalidValueException::class, 'scheduling.availability.invalid_range')
        ->and(fn () => TimeRange::create('09:00', '09:00'))
        ->toThrow(InvalidValueException::class);
});
