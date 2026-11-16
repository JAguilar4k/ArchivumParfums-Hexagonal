<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Application\DTOs;

/** The attendance a person of the staff records for one appointment: whether the customer came to the trial. */
final readonly class RecordAttendanceCommand
{
    /** Hold the appointment and what happened to it. */
    public function __construct(public string $appointmentId, public bool $attended) {}
}
