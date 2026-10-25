<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\ValueObjects;

enum MovementType: string
{
    case Reservation = 'reservation';
    case Release = 'release';
    case Consumption = 'consumption';
    case Adjustment = 'adjustment';
}
