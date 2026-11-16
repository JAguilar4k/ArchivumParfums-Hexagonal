<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** A service of the store as booking sees it: what it lasts, the margin it keeps and how many perfumes it allows. */
final readonly class ServiceOption
{
    /** Hold the identity, the name the customer reads and the numbers the appointment needs. */
    public function __construct(
        public string $id,
        public string $name,
        public int $durationMinutes,
        public int $bufferMinutes,
        public int $maxPerfumes,
    ) {}
}
