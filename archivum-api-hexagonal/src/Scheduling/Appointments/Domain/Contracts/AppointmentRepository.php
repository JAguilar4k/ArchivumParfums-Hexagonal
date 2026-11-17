<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Contracts;

use Src\Scheduling\Appointments\Domain\Entities\Appointment;
use Src\Scheduling\Appointments\Domain\Exceptions\AppointmentSlotTakenException;
use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentId;

/**
 * Port that stores and rebuilds the aggregate of an appointment. The customer listings and the agenda read their own
 * projections (#64, #137) instead of walking through the aggregate.
 */
interface AppointmentRepository
{
    /** Reserve the identifier of the next appointment before it is built. */
    public function nextId(): AppointmentId;

    /** Find an appointment of the business of the context; one of another business is not found. */
    public function find(AppointmentId $id): ?Appointment;

    /**
     * Store the appointment and its perfumes in one transaction.
     *
     * @throws AppointmentSlotTakenException when another active appointment already blocks the slot
     */
    public function save(Appointment $appointment): void;
}
