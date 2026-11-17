<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** An appointment that chooses the same perfume twice; the API answers 422. */
final class RepeatedPerfumeException extends InvalidValueException
{
    /** Build the exception with the key of its message. */
    public static function create(): self
    {
        return new self('scheduling.appointment.repeated_perfume');
    }
}
