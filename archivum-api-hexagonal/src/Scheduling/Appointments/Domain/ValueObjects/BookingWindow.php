<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * The window in which a store takes the start of an appointment (R13): no sooner than the notice it asks for and no
 * later than the last day of its horizon, counted in the time zone of the store. Booking an appointment and moving it
 * check their start against the same window.
 */
final readonly class BookingWindow
{
    /** Keep the first instant the store takes and the midnight of the last day it takes. */
    private function __construct(
        private DateTimeImmutable $earliest,
        private DateTimeImmutable $lastDay,
        private DateTimeZone $zone,
    ) {}

    /** The window open at that instant with the notice and the horizon of the store. */
    public static function of(DateTimeImmutable $now, int $minimumNoticeHours, int $bookingHorizonDays, DateTimeZone $zone): self
    {
        $local = $now->setTimezone($zone);

        return new self(
            $local->modify("+{$minimumNoticeHours} hours"),
            $local->setTime(0, 0)->modify("+{$bookingHorizonDays} days"),
            $zone,
        );
    }

    /**
     * Read the start a request sends, an ISO 8601 instant with its offset.
     *
     * @throws InvalidValueException when the text is not an instant
     */
    public static function startFrom(string $startsAt): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($startsAt);
        } catch (Exception) {
            throw new InvalidValueException('scheduling.booking.invalid_start');
        }
    }

    /** Midnight of the day of the store the start falls on. */
    public function dayOf(DateTimeImmutable $startsAt): DateTimeImmutable
    {
        return $startsAt->setTimezone($this->zone)->setTime(0, 0);
    }

    /** @throws InvalidValueException when the start comes before the notice or after the last day of the horizon */
    public function assertAllows(DateTimeImmutable $startsAt): void
    {
        if ($startsAt < $this->earliest || $this->dayOf($startsAt) > $this->lastDay) {
            throw new InvalidValueException('scheduling.booking.outside_window');
        }
    }
}
