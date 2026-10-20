<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

enum TimeOfDay: string
{
    case Day = 'day';
    case Afternoon = 'afternoon';
    case Night = 'night';
}
