<?php

declare(strict_types=1);

use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentChangeNotAllowedException;
use Src\Scheduling\Appointments\Domain\Exceptions\InvalidAppointmentTransitionException;
use Src\Scheduling\Appointments\Domain\Exceptions\TooManyPerfumesException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentStatus;
use Src\Scheduling\Appointments\Domain\ValueObjects\CustomerId;
use Src\Scheduling\Appointments\Domain\ValueObjects\PerfumeId;
use Src\Scheduling\Appointments\Domain\ValueObjects\ServiceId;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

/**
 * Arguments of a valid appointment, shared by the tests of the aggregate.
 *
 * @return array{0: AppointmentId, 1: BusinessId, 2: ServiceId, 3: CustomerId, 4: MemberId, 5: DateTimeImmutable, 6: int, 7: int, 8: list<PerfumeId>, 9: int}
 */
function appointmentArgs(): array
{
    return [
        new AppointmentId('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f70'),
        new BusinessId('019a1f2e-7c3b-7d4e-9a11-2b3c4d5e6f71'),
        new ServiceId('019a1f2e-7c3b-7d4e-9a12-2b3c4d5e6f72'),
        new CustomerId('019a1f2e-7c3b-7d4e-9a13-2b3c4d5e6f73'),
        new MemberId('019a1f2e-7c3b-7d4e-9a14-2b3c4d5e6f74'),
        new DateTimeImmutable('2026-11-20T17:00:00-06:00'),
        45,
        15,
        [new PerfumeId('019a1f2e-7c3b-7d4e-9a15-2b3c4d5e6f75')],
        5,
    ];
}

/** A confirmed appointment of the first of the two dates of the tests. */
function bookedAppointment(): Appointment
{
    return Appointment::book(...appointmentArgs());
}

test('books a confirmed appointment and calculates its end and its blocked range', function (): void {
    $appointment = bookedAppointment();

    expect($appointment->status())->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->startsAt()->format(DATE_ATOM))->toBe('2026-11-20T17:00:00-06:00')
        ->and($appointment->endsAt()->format(DATE_ATOM))->toBe('2026-11-20T17:45:00-06:00')
        ->and($appointment->blockedUntil()->format(DATE_ATOM))->toBe('2026-11-20T18:00:00-06:00')
        ->and($appointment->perfumes())->toHaveCount(1);
});

test('refuses more perfumes than the service allows', function (): void {
    $args = appointmentArgs();
    $args[8] = [
        new PerfumeId('019a1f2e-7c3b-7d4e-9a15-2b3c4d5e6f75'),
        new PerfumeId('019a1f2e-7c3b-7d4e-9a16-2b3c4d5e6f76'),
    ];
    $args[9] = 1;

    expect(fn () => Appointment::book(...$args))
        ->toThrow(TooManyPerfumesException::class, 'scheduling.appointment.too_many_perfumes');
});

test('refuses a perfume chosen twice', function (): void {
    $args = appointmentArgs();
    $args[8] = [
        new PerfumeId('019a1f2e-7c3b-7d4e-9a15-2b3c4d5e6f75'),
        new PerfumeId('019a1f2e-7c3b-7d4e-9a15-2b3c4d5e6f75'),
    ];

    expect(fn () => Appointment::book(...$args))
        ->toThrow(InvalidValueException::class, 'scheduling.appointment.repeated_perfume');
});

test('walks the states of section six and refuses the transitions it does not allow', function (): void {
    $appointment = bookedAppointment();

    $appointment->hold(new DateTimeImmutable('2026-11-19T10:00:00-06:00'));

    expect($appointment->status())->toBe(AppointmentStatus::Held)
        ->and($appointment->holdExpiresAt())->not->toBeNull();

    $appointment->confirm();

    expect($appointment->status())->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->holdExpiresAt())->toBeNull();

    $appointment->complete();

    expect($appointment->status())->toBe(AppointmentStatus::Completed);

    expect(fn () => $appointment->cancel(new DateTimeImmutable('2026-11-19T10:00:00-06:00'), 2))
        ->toThrow(InvalidAppointmentTransitionException::class, 'scheduling.appointment.invalid_transition')
        ->and(fn () => $appointment->markNoShow())
        ->toThrow(InvalidAppointmentTransitionException::class)
        ->and(fn () => $appointment->expire())
        ->toThrow(InvalidAppointmentTransitionException::class);
});

test('holds an offer and lets it expire', function (): void {
    $appointment = bookedAppointment();
    $appointment->hold(new DateTimeImmutable('2026-11-19T10:00:00-06:00'));
    $appointment->expire();

    expect($appointment->status())->toBe(AppointmentStatus::Expired);

    expect(fn () => $appointment->confirm())->toThrow(InvalidAppointmentTransitionException::class);
});

test('cancels before the deadline and keeps the date and the reason', function (): void {
    $appointment = bookedAppointment();
    $now = new DateTimeImmutable('2026-11-20T10:00:00-06:00');

    $appointment->cancel($now, 2, 'Surgió un imprevisto');

    expect($appointment->status())->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->cancelledAt()?->format(DATE_ATOM))->toBe('2026-11-20T10:00:00-06:00')
        ->and($appointment->cancelReason())->toBe('Surgió un imprevisto');
});

test('refuses to cancel or move the appointment inside the deadline', function (): void {
    $appointment = bookedAppointment();
    $inside = new DateTimeImmutable('2026-11-20T16:00:00-06:00');

    expect(fn () => $appointment->cancel($inside, 2))
        ->toThrow(AppointmentChangeNotAllowedException::class, 'scheduling.appointment.change_not_allowed')
        ->and(fn () => $appointment->reschedule(new DateTimeImmutable('2026-11-27T17:00:00-06:00'), $inside, 2))
        ->toThrow(AppointmentChangeNotAllowedException::class);

    expect($appointment->status())->toBe(AppointmentStatus::Confirmed);
});

test('moves the appointment and recalculates its end and its blocked range', function (): void {
    $appointment = bookedAppointment();
    $now = new DateTimeImmutable('2026-11-20T10:00:00-06:00');

    $appointment->reschedule(new DateTimeImmutable('2026-11-27T18:00:00-06:00'), $now, 2);

    expect($appointment->startsAt()->format(DATE_ATOM))->toBe('2026-11-27T18:00:00-06:00')
        ->and($appointment->endsAt()->format(DATE_ATOM))->toBe('2026-11-27T18:45:00-06:00')
        ->and($appointment->blockedUntil()->format(DATE_ATOM))->toBe('2026-11-27T19:00:00-06:00')
        ->and($appointment->status())->toBe(AppointmentStatus::Confirmed);
});

test('marks a confirmed appointment as not attended', function (): void {
    $appointment = bookedAppointment();
    $appointment->markNoShow();

    expect($appointment->status())->toBe(AppointmentStatus::NoShow)
        ->and($appointment->status()->occupiesSlot())->toBeFalse();
});

test('rebuilds a stored appointment and refuses an inconsistent range', function (): void {
    $args = appointmentArgs();
    $stored = Appointment::reconstitute(
        $args[0], $args[1], $args[2], $args[3], $args[4],
        new DateTimeImmutable('2026-11-20T17:00:00-06:00'),
        new DateTimeImmutable('2026-11-20T17:45:00-06:00'),
        new DateTimeImmutable('2026-11-20T18:00:00-06:00'),
        $args[8],
        AppointmentStatus::Cancelled,
        null,
        new DateTimeImmutable('2026-11-19T09:00:00-06:00'),
        'Viaje',
    );

    expect($stored->status())->toBe(AppointmentStatus::Cancelled)
        ->and($stored->cancelReason())->toBe('Viaje');

    expect(fn () => Appointment::reconstitute(
        $args[0], $args[1], $args[2], $args[3], $args[4],
        new DateTimeImmutable('2026-11-20T17:00:00-06:00'),
        new DateTimeImmutable('2026-11-20T16:45:00-06:00'),
        new DateTimeImmutable('2026-11-20T18:00:00-06:00'),
        $args[8],
        AppointmentStatus::Confirmed,
    ))->toThrow(InvalidValueException::class, 'scheduling.appointment.invalid_order');
});
