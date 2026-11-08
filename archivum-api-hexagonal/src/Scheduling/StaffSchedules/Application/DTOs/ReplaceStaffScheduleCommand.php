<?php

declare(strict_types=1);

namespace Src\Scheduling\StaffSchedules\Application\DTOs;

/** The whole working hours of one person that the panel sends to replace. */
final readonly class ReplaceStaffScheduleCommand
{
    /**
     * Receive the person and the tramos already validated by the request; an empty list leaves the person without hours.
     *
     * @param  list<StaffScheduleSlot>  $slots
     */
    public function __construct(
        public string $memberId,
        public array $slots,
    ) {}
}
