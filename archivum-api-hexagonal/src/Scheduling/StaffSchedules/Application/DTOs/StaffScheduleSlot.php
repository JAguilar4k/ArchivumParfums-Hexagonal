<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Application\DTOs;

/** One tramo of the working hours of a person: a weekday from 1 to 7 and two local hours in HH:MM. */
final readonly class StaffScheduleSlot
{
    /** Receive the values already validated by the request. */
    public function __construct(
        public int $weekday,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
