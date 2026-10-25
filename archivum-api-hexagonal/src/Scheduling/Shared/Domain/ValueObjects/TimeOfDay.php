<?php

namespace Src\Scheduling\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class TimeOfDay
{
    private function __construct(public int $minutes) {}

    public static function fromString(string $time): self
    {
        // Validates the HH:MM format strictly
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            throw new InvalidValueException('scheduling.invalid_time_format');
        }
        
        [$hours, $minutes] = explode(':', $time);
        return new self(((int) $hours * 60) + (int) $minutes);
    }

    public function isAfter(self $other): bool
    {
        return $this->minutes > $other->minutes;
    }

    public function equals(self $other): bool
    {
        return $this->minutes === $other->minutes;
    }
}