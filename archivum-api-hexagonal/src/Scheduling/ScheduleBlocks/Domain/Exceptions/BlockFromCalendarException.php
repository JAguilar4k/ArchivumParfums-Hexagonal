<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/**
 * A block that comes from another system, such as Google Calendar (#183), cannot be edited or deleted from the panel;
 * the API answers 409.
 */
final class BlockFromCalendarException extends DomainException
{
    /** Build the exception with the key the API translates. */
    public static function create(): self
    {
        return new self('scheduling.schedule_block.from_calendar');
    }
}
