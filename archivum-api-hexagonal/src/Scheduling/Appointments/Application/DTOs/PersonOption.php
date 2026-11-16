<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** A person of the staff who attends appointments, with the name the customer reads. */
final readonly class PersonOption
{
    /** Hold the identity and the name of the person. */
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
