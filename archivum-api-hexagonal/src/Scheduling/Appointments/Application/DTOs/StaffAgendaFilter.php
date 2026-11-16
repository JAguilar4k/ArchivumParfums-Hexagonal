<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

use DateTimeImmutable;

/**
 * The window of the agenda of the staff: the instants where it starts and ends, both in the time zone of the store, and
 * the optional person and state that narrow it. The end is exclusive, so a day is [its start, the start of the next one).
 */
final readonly class StaffAgendaFilter
{
    /** Hold the window and the two filters the panel may send. */
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public ?string $memberId = null,
        public ?string $status = null,
    ) {}
}
