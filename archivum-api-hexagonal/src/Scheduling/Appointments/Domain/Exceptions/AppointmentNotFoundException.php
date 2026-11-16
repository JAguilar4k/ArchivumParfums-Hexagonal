<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

/** An appointment that does not exist, or that belongs to another customer; the API answers 404. */
final class AppointmentNotFoundException extends NotFoundException
{
    /** Build the exception of an appointment the customer cannot read. */
    public static function create(): self
    {
        return new self('scheduling.appointment.not_found');
    }
}
