<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

/** The terms of the booking window the availability of a store follows. */
final readonly class StoreAvailability
{
    /** Hold the time zone of the store and the hours of notice and the days of horizon it allows. */
    public function __construct(
        public string $timezone,
        public int $minimumNoticeHours,
        public int $bookingHorizonDays,
    ) {}
}
