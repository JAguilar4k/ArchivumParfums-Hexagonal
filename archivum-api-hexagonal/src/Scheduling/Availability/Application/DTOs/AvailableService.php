<?php

declare(strict_types=1);

namespace Src\Scheduling\Availability\Application\DTOs;

/** The part of a service the availability of a day needs: how long it lasts and the margin kept after it. */
final readonly class AvailableService
{
    /** Hold the duration of the appointment and the margin that stays blocked after it. */
    public function __construct(
        public int $durationMinutes,
        public int $bufferMinutes,
    ) {}
}
