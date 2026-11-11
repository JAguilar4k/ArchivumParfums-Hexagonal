<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/** An appointment with more perfumes than the service allows, or with one perfume repeated; the API answers 422. */
final class TooManyPerfumesException extends InvalidValueException
{
    /** Build the exception with the limit of the service. */
    public static function forLimit(int $maxPerfumes): self
    {
        return new self('scheduling.appointment.too_many_perfumes', ['max' => $maxPerfumes]);
    }
}
