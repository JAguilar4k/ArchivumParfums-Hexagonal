<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Application\DTOs;

/** One tramo of the opening hours as the panel sends it: a weekday from 1 to 7 and two local hours in HH:MM. */
final readonly class BusinessHoursSlot
{
    /** Receive the values already validated by the request. */
    public function __construct(
        public int $weekday,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
