<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** The terms of the booking window of a store: its time zone, its minimum notice and its horizon. */
final readonly class BookingTerms
{
    /** Hold the time zone and the two limits the availability of #61 also applies. */
    public function __construct(
        public string $timezone,
        public int $minimumNoticeHours,
        public int $bookingHorizonDays,
    ) {}
}
