<?php

namespace Src\Scheduling\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeRange
{
    private function __construct(
        public TimeOfDay $start,
        public TimeOfDay $end
    ) {
        if (!$end->isAfter($start)) {
            throw new InvalidValueException('scheduling.time_range_invalid_order');
        }
    }

    public static function create(string $start, string $end): self
    {
        return new self(
            TimeOfDay::fromString($start),
            TimeOfDay::fromString($end)
        );
    }

    /**
     * Checks if this range overlaps with another.
     * Evaluates as a semi-open interval [start, end).
     */
    public function overlaps(TimeRange $other): bool
    {
        return $this->start->minutes < $other->end->minutes 
            && $this->end->minutes > $other->start->minutes;
    }
}