<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/** A person of the staff who does not attend appointments yet, so the panel cannot give her hours; the API answers 409. */
final class MemberDoesNotAttendAppointmentsException extends DomainException
{
    /** Build the exception with the key the API translates. */
    public static function create(): self
    {
        return new self('scheduling.staff_schedule.member_not_attending');
    }
}
