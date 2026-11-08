<?php

declare(strict_types=1);

namespace Src\Scheduling\Appointments\Domain\ValueObjects;

/** The six states of an appointment of section 6 of the specification; only held and confirmed occupy the slot. */
enum AppointmentStatus: string
{
    case Confirmed = 'confirmed';
    case Held = 'held';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Expired = 'expired';

    /** Whether the appointment takes the slot, as the exclusion constraint of the table understands it. */
    public function occupiesSlot(): bool
    {
        return $this === self::Confirmed || $this === self::Held;
    }

    /** Whether the appointment is still in the hands of the customer to change it. */
    public function isActive(): bool
    {
        return $this->occupiesSlot();
    }
}
