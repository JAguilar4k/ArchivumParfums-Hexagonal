<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Domain\ValueObjects;

/** A slot nobody occupies yet, with the people of the staff who can attend it. */
final readonly class FreeSlot
{
    /**
     * @param  list<PersonDay>  $people  The people free for this slot, ordered by name.
     */
    public function __construct(
        public MinuteRange $range,
        public array $people,
    ) {}
}
