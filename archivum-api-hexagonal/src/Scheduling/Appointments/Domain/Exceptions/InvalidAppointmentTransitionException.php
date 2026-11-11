<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Scheduling\Appointments\Domain\ValueObjects\AppointmentStatus;
use Src\Shared\Domain\Exceptions\DomainException;

/** A change of state the appointment does not allow, such as cancelling one already attended; the API answers 409. */
final class InvalidAppointmentTransitionException extends DomainException
{
    /** Build the exception with the state it has and the one it was asked to reach. */
    public static function from(AppointmentStatus $current, AppointmentStatus $target): self
    {
        return new self('scheduling.appointment.invalid_transition', [
            'from' => $current->value,
            'to' => $target->value,
        ]);
    }
}
