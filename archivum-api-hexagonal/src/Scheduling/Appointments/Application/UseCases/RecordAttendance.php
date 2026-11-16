<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\UseCases;

use Src\Scheduling\Appointments\Application\Contracts\AttendanceMembers;
use Src\Scheduling\Appointments\Application\Contracts\CustomerAppointments;
use Src\Scheduling\Appointments\Application\DTOs\AppointmentView;
use Src\Scheduling\Appointments\Application\DTOs\RecordAttendanceCommand;
use Src\Scheduling\Appointments\Domain\Contracts\AppointmentRepository;
use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentNotFoundException;
use Src\Scheduling\Appointments\Domain\Exceptions\InvalidAppointmentTransitionException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * Mark a confirmed appointment as attended or as not attended (#138), over the state machine of the aggregate of #58.
 * The owner marks any appointment of its store and a collaborator only the ones it attends; the appointment of another
 * person answers as one that does not exist, so the answer never reveals that it is there.
 */
final readonly class RecordAttendance
{
    /** Receive the repository, the person of the session and the reader that answers the result. */
    public function __construct(
        private AppointmentRepository $appointments,
        private AttendanceMembers $members,
        private CustomerAppointments $views,
    ) {}

    /**
     * Record the attendance and answer the appointment as the panel shows it.
     *
     * @param  bool  $seesEveryPerson  whether the account carries the ability of the owner over the store
     *
     * @throws AppointmentNotFoundException when the appointment is not of the store or not of that person
     * @throws InvalidAppointmentTransitionException when it is not confirmed, including one already marked
     */
    public function execute(RecordAttendanceCommand $command, string $userId, bool $seesEveryPerson): AppointmentView
    {
        $appointment = $this->appointment($command->appointmentId);

        if (! $seesEveryPerson && $appointment->memberId->value !== $this->members->memberOf($userId)) {
            throw AppointmentNotFoundException::create();
        }

        if ($command->attended) {
            $appointment->complete();
        } else {
            $appointment->markNoShow();
        }

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
