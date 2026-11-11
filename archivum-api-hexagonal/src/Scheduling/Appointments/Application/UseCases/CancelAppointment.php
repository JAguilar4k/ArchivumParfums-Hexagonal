<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use DateTimeImmutable;
use DateTimeZone;
use Src\Scheduling\Appointments\Application\Contracts\BookingCatalog;
use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Application\DTOs\CancelAppointmentCommand;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentChangeNotAllowedException;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentNotFoundException;
use Src\Scheduling\Appointments\Domain\Exceptions\InvalidAppointmentTransitionException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * Cancel a confirmed appointment (R16). The row is never deleted: it keeps its date and the reason, so the history stays,
 * and the exclusion constraint stops counting it, which frees the slot at once.
 */
final readonly class CancelAppointment
{
    /** Receive the repository, the terms of the store and the reader that answers the result. */
    public function __construct(
        private AppointmentRepository $appointments,
        private BookingCatalog $catalog,
        private CustomerAppointments $views,
    ) {}

    /**
     * Cancel the appointment and answer it as the API shows it.
     *
     * @throws AppointmentNotFoundException when the appointment is not of the store of the context
     * @throws InvalidAppointmentTransitionException when it is not confirmed
     * @throws AppointmentChangeNotAllowedException after the deadline
     */
    public function execute(CancelAppointmentCommand $command): AppointmentView
    {
        $terms = $this->catalog->terms();
        $zone = new DateTimeZone($terms->timezone);
        $appointment = $this->appointment($command->appointmentId);

        // The customer answers to the deadline the store gives. The store itself has no deadline of its own in the
        // specification, so it may cancel until the appointment starts; the item stays open in the issue.
        $cutoffHours = $command->customerDeadline ? $terms->changeCutoffHours : 0;

        $appointment->cancel(new DateTimeImmutable('now', $zone), $cutoffHours, $command->reason);

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
}
