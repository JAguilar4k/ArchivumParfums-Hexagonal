<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** The terms of the booking window of a store: its time zone, its minimum notice, its horizon and the deadline to move a booked appointment. */
final readonly class BookingTerms
{
    /** Hold the time zone, the two limits the availability of #61 also applies and the hours the customer has to change an appointment. */
    public function __construct(
        public string $timezone,
        public int $minimumNoticeHours,
        public int $bookingHorizonDays,
        public int $changeCutoffHours,
    ) {}
}
