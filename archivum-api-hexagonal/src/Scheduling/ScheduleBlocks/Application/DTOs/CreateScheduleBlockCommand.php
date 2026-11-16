<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\DTOs;

use DateTimeImmutable;

/** What the panel sends to block a range of the agenda; a null member blocks the whole store. */
final readonly class CreateScheduleBlockCommand
{
    /** Receive the values already validated by the request. */
    public function __construct(
        public ?string $memberId,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public string $reason,
    ) {}
}
