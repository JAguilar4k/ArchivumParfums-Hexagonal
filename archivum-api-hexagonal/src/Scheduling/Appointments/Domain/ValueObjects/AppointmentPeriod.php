<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

/** The two periods the customer can list: what is still ahead and what already happened. */
enum AppointmentPeriod: string
{
    case Upcoming = 'proximas';
    case Past = 'pasadas';
}
