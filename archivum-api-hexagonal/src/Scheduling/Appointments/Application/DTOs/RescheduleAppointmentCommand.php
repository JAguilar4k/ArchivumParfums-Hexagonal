<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** What the customer or the panel asked to change: the appointment and its new start. */
final readonly class RescheduleAppointmentCommand
{
    /** Hold the appointment to move and the new start, as the ISO 8601 instant the request validated. */
    public function __construct(
        public string $appointmentId,
        public string $startsAt,
    ) {}
}
