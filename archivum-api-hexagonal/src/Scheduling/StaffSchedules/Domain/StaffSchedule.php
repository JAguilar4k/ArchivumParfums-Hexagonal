<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain;

use Exception;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;

final class StaffSchedule
{
    /** @var array<int, TimeRange[]> */
    private array $slots = [];

    private function __construct(
        private readonly string $memberId
    ) {}

    public static function create(string $memberId): self
    {
        return new self($memberId);
    }

    public static function reconstitute(string $memberId, array $slots): self
    {
        $schedule = new self($memberId);
        $schedule->slots = $slots;

        return $schedule;
    }

    public function addSlot(Weekday $day, TimeRange $newRange): void
    {
        $dayValue = $day->value;

        if (! isset($this->slots[$dayValue])) {
            $this->slots[$dayValue] = [];
        }

        foreach ($this->slots[$dayValue] as $existingRange) {
            if ($existingRange->overlaps($newRange)) {
                throw new Exception('scheduling.staff_schedule.overlapping_slots');
            }
        }

        $this->slots[$dayValue][] = $newRange;
    }

    public function getSlots(): array
    {
        return $this->slots;
    }
}
