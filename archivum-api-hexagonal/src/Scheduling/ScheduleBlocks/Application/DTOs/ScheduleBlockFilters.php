<?php

declare(strict_types=1);

namespace Src\Scheduling\ScheduleBlocks\Application\DTOs;

use DateTimeImmutable;

/** The range the panel asks for, with an optional person; the availability of #61 uses the same range idea. */
final readonly class ScheduleBlockFilters
{
    /** Receive the instants and the person already validated by the request. */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public ?string $memberId,
    ) {}
}
