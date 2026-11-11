<?php

declare(strict_types=1);

namespace Src\Scheduling\BusinessHours\Application\DTOs;

/** The whole schedule the panel sends to replace the one of the business of the session. */
final readonly class ReplaceBusinessHoursCommand
{
    /**
     * Receive the tramos already validated by the request; an empty list leaves the store without opening hours.
     *
     * @param  list<BusinessHoursSlot>  $slots
     */
    public function __construct(public array $slots) {}
}
