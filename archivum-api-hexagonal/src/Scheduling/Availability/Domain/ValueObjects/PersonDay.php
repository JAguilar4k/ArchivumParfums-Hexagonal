<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Domain\ValueObjects;

/**
 * One person of the staff as the availability sees them on the day being calculated: the tramos of the store they can
 * work, the blocks that take them out of the agenda and the appointments that already occupy their slot.
 */
final readonly class PersonDay
{
    /**
     * @param  list<MinuteRange>  $workingHours  Their own tramos for that weekday, in minutes of the day.
     * @param  list<MinuteRange>  $blocks  Their own blocks and the ones of the whole store, as [start, end).
     * @param  list<MinuteRange>  $appointments  The active appointments of the day, as [starts_at, blocked_until).
     */
    public function __construct(
        public string $memberId,
        public string $name,
        public array $workingHours,
        public array $blocks,
        public array $appointments,
    ) {}
}
