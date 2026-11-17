<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

/** A person of the staff that does not exist in this business, which answers 404 and never reveals that it exists. */
final class MemberNotFoundException extends NotFoundException
{
    /** Build the exception with the key the API translates. */
    public static function create(): self
    {
        return new self('scheduling.staff_schedule.member_not_found');
    }
}
