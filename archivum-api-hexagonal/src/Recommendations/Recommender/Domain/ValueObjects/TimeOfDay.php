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

    /**
     * The words that name this time of day in a customer's text, in lowercase and without accents, as the rules
     * recommender compares them (#120).
     *
     * @return list<string>
     */
    public function words(): array
    {
        return match ($this) {
            self::Day => ['dia', 'diurno', 'diurna', 'manana', 'mediodia'],
            self::Afternoon => ['tarde', 'atardecer'],
            self::Night => ['noche', 'nocturno', 'nocturna'],
        };
    }
}
