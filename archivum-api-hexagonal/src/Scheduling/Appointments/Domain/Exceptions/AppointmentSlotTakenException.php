<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/**
 * Another active appointment already blocks the slot of the same person. The exclusion constraint of the table
 * answers it as SQLSTATE 23P01 and the repository translates it outside the aborted transaction; the API answers 409.
 */
final class AppointmentSlotTakenException extends DomainException
{
    /** Build the exception whose message explains that the slot is no longer free. */
    public static function create(): self
    {
        return new self('scheduling.appointment.slot_taken');
    }
}
