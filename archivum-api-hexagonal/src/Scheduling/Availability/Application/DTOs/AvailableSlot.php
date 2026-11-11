<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

use DateTimeImmutable;

/** A free slot of the day with the people who can attend it. */
final readonly class AvailableSlot
{
    /**
     * @param  list<AvailablePerson>  $people  The people free for the slot, ordered by name.
     */
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public array $people,
    ) {}
}
