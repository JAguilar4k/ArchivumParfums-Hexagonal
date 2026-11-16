<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/**
 * What the customer or the panel asked to cancel. The customer answers to the deadline of the store; the store itself
 * may cancel while the appointment has not started.
 */
final readonly class CancelAppointmentCommand
{
    /** Hold the appointment, the reason the customer wrote and whether the deadline of the customer applies. */
    public function __construct(
        public string $appointmentId,
        public ?string $reason = null,
        public bool $customerDeadline = true,
    ) {}
}
