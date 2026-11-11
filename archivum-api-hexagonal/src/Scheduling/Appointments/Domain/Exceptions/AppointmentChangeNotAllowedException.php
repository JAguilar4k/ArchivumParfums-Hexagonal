<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/** A change asked after the deadline the store gives the customer to move or cancel the appointment; the API answers 409. */
final class AppointmentChangeNotAllowedException extends DomainException
{
    /** Build the exception with the hours of the deadline, so the message can explain it. */
    public static function forDeadline(int $hours): self
    {
        return new self('scheduling.appointment.change_not_allowed', ['hours' => $hours]);
    }
}
