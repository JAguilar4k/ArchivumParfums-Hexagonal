<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Validation\ValidationException;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Application\DTOs\BookingTerms;
use Src\Scheduling\Appointments\Application\DTOs\RescheduleAppointmentCommand;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentNotFoundException;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentSlotTakenException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Scheduling\Availability\Application\UseCases\GetAvailability;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * Move an appointment to another free slot (R13, R14, R16). It keeps the person, the duration and the margin of the
 * appointment and recalculates its end and its blocked range from the new start. The new slot is checked against the
 * real availability without counting the appointment itself, and the exclusion constraint of #59 is the last barrier.
 */
final readonly class RescheduleAppointment
{
    /** Receive the repository, the terms of the store, the availability and the reader that answers the result. */
    public function __construct(
        private AppointmentRepository $appointments,
        private BookingCatalog $catalog,
        private GetAvailability $availability,
        private CustomerAppointments $views,
    ) {}

    /**
     * Move the appointment and answer it as the API shows it.
     *
     * @throws AppointmentNotFoundException when the appointment is not of the store of the context
     * @throws ValidationException when the new start is malformed or outside the booking window
     * @throws AppointmentSlotTakenException when the slot is not free
     */
    public function execute(RescheduleAppointmentCommand $command): AppointmentView
    {
        $terms = $this->catalog->terms();
        $zone = new DateTimeZone($terms->timezone);
        $startsAt = $this->startsAt($command->startsAt);
        $day = $startsAt->setTimezone($zone)->setTime(0, 0);

        $appointment = $this->appointment($command->appointmentId);

        $this->assertInsideWindow($startsAt, $day, $terms, $zone);

        // The aggregate refuses a state it cannot move and a change asked after the deadline the store gives.
        $appointment->reschedule(
            $this->forStorage($startsAt),
            new DateTimeImmutable('now', $zone),
            $terms->changeCutoffHours,
        );

        $this->assertSlotIsFree($appointment, $day, $startsAt);

        $this->appointments->save($appointment);

        return $this->views->viewOf($appointment->id->value) ?? throw AppointmentNotFoundException::create();
    }

    /** @throws AppointmentNotFoundException */
    private function appointment(string $id): Appointment
    {
        try {
            $appointmentId = new AppointmentId($id);
        } catch (InvalidValueException) {
            throw AppointmentNotFoundException::create();
        }

        return $this->appointments->find($appointmentId) ?? throw AppointmentNotFoundException::create();
    }

    /**
     * The new start must be one of the free slots of that service and person, calculated without the appointment
     * itself so moving it inside its own range is allowed.
     *
     * @throws AppointmentSlotTakenException
     */
    private function assertSlotIsFree(Appointment $appointment, DateTimeImmutable $day, DateTimeImmutable $startsAt): void
    {
        $slots = $this->availability->execute(
            $appointment->serviceId->value,
            $day->format('Y-m-d'),
            $appointment->memberId->value,
            $appointment->id->value,
        );

        foreach ($slots as $slot) {
            if ($slot->startsAt->getTimestamp() === $startsAt->getTimestamp()) {
                return;
            }
        }

        throw AppointmentSlotTakenException::create();
    }

    /** The instant must fall between the notice the store gives and the end of its horizon. */
    private function assertInsideWindow(DateTimeImmutable $startsAt, DateTimeImmutable $day, BookingTerms $terms, DateTimeZone $zone): void
    {
        $now = new DateTimeImmutable('now', $zone);
        $lastDay = $now->setTime(0, 0)->modify("+{$terms->bookingHorizonDays} days");

        if ($startsAt < $now->modify("+{$terms->minimumNoticeHours} hours") || $day > $lastDay) {
            throw ValidationException::withMessages(['inicia_en' => __('scheduling.booking.outside_window')]);
        }
    }

    /** @throws ValidationException when the text is not an ISO 8601 instant with its offset */
    private function startsAt(string $startsAt): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($startsAt);
        } catch (Exception) {
            throw ValidationException::withMessages(['inicia_en' => __('scheduling.booking.invalid_start')]);
        }
    }

    /** The instant as the connection stores it, so the slot compares the same way the reader of #61 reads it. */
    private function forStorage(DateTimeImmutable $instant): DateTimeImmutable
    {
        return $instant->setTimezone(new DateTimeZone((string) config('database.connections.pgsql.timezone', 'UTC')));
    }
}
