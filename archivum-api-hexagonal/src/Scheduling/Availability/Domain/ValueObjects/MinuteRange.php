<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * A half-open range of minutes counted from the midnight of the day the availability is calculated for. A range may
 * start before zero or end after 1440, so a block that crosses midnight still compares against the slots of the day.
 */
final readonly class MinuteRange
{
    /** Hold the two ends of the range; an empty or reversed range is a programming error. */
    public function __construct(public int $start, public int $end)
    {
        if ($end <= $start) {
            throw new InvalidValueException('scheduling.availability.invalid_range');
        }
    }

    /** Whether the two half-open ranges share at least one minute. */
    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    /** Length of the range in minutes. */
    public function length(): int
    {
        return $this->end - $this->start;
    }
}
