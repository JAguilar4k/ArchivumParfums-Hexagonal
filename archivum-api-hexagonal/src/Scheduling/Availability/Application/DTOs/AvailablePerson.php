<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

/** The public identity of a person of the staff in one slot of the availability. */
final readonly class AvailablePerson
{
    /** Hold the identifier and the name the visitor reads. */
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
