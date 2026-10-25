<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Domain\Entities;

use Src\Scheduling\Shared\Domain\Exceptions\TimeSlotOverlapsException;
use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;
use Src\Scheduling\Shared\Domain\ValueObjects\Weekday;
use Src\Scheduling\StaffSchedules\Domain\ValueObjects\StaffScheduleId;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\MemberId;

final class StaffSchedule
{
    /** @var array<int, TimeRange[]> */
    private array $slots = [];

    private function __construct(
        public readonly StaffScheduleId $id,
        public readonly BusinessId $businessId,
        public readonly MemberId $memberId
    ) {}

    public static function create(
        StaffScheduleId $id,
        BusinessId $businessId,
        MemberId $memberId
    ): self {
        return new self($id, $businessId, $memberId);
    }

    /**
     * @param  array<int, array<int, TimeRange>>  $slots
     */
    public static function reconstitute(
        StaffScheduleId $id,
        BusinessId $businessId,
        MemberId $memberId,
        array $slots
    ): self {
        $schedule = new self($id, $businessId, $memberId);
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
