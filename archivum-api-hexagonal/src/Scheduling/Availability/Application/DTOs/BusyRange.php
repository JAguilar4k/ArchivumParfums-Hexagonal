<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

use DateTimeImmutable;

/** A range of instants the agenda already occupies, either a block or the range an active appointment keeps. */
final readonly class BusyRange
{
    /** Hold the two ends of the range. */
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {}
}
