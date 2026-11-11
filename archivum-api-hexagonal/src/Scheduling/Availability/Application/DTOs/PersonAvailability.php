<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

use Src\Scheduling\Shared\Domain\ValueObjects\TimeRange;

/** One person of the staff with what the availability of the day needs to know about them. */
final readonly class PersonAvailability
{
    /**
     * @param  list<TimeRange>  $workingHours  Their own tramos of that weekday.
     * @param  list<BusyRange>  $blocks  Their own blocks and the ones of the whole store, which cross the day.
     * @param  list<BusyRange>  $appointments  Their active appointments, as the range each one keeps blocked.
     */
    public function __construct(
        public string $memberId,
        public string $name,
        public array $workingHours,
        public array $blocks,
        public array $appointments,
    ) {}
}
