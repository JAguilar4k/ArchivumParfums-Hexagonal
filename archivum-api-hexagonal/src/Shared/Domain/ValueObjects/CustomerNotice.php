<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

enum CustomerNotice: string
{
    case OrderPlaced = 'order_placed';
    case AppointmentBooked = 'appointment_booked';
}
