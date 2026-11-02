<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

/**
 * A time of day the customer can ask for, with the values the catalog tags its perfumes with (R5). The recommender
 * keeps its own copy, so its domain does not depend on the catalog's.
 */
enum TimeOfDay: string
{
    case Day = 'day';
    case Afternoon = 'afternoon';
    case Night = 'night';
}
