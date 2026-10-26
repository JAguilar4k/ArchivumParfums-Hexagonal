<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Domain\Entities;

use Src\Scheduling\BusinessHours\Domain\ValueObjects\BusinessHoursId;
use Src\Scheduling\Shared\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class BusinessHours
{
    /** @var array<int, TimeRange[]> */
    private array $slots = [];

    private function __construct(
        public readonly BusinessHoursId $id,
        public readonly BusinessId $businessId
    ) {}

    public static function create(
        BusinessHoursId $id,
        BusinessId $businessId
    ): self {
        return new self($id, $businessId);
    }

    /**
     * @param  array<int, array<int, TimeRange>>  $slots
     */
    public static function reconstitute(
        BusinessHoursId $id,
        BusinessId $businessId,
        array $slots
    ): self {
        $schedule = new self($id, $businessId);
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
                throw new TimeSlotOverlapsException('scheduling.time_slot_overlaps');
            }
        }

        $this->slots[$dayValue][] = $newRange;
    }

    /**
     * @return array<int, array<int, TimeRange>>
     */
    public function getSlots(): array
    {
        return $this->slots;
    }
}
