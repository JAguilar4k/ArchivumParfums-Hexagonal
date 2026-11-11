<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

/** A block that does not exist, or that belongs to another business, which answers 404 and never reveals it exists. */
final class ScheduleBlockNotFoundException extends NotFoundException
{
    /** Build the exception with the key the API translates. */
    public static function create(): self
    {
        return new self('scheduling.schedule_block.not_found');
    }
}
