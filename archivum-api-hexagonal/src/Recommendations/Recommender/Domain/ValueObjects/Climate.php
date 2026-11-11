<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

/**
 * A climate the customer can ask for, with the values the catalog tags its perfumes with (R5). The recommender keeps
 * its own copy, so its domain does not depend on the catalog's.
 */
enum Climate: string
{
    case Warm = 'warm';
    case Temperate = 'temperate';
    case Cold = 'cold';

    /**
     * The words that name this climate in a customer's text, in lowercase and without accents, as the rules
     * recommender compares them (#120).
     *
     * @return list<string>
     */
    public function words(): array
    {
        return match ($this) {
            self::Warm => ['calido', 'calida', 'calor', 'caluroso', 'calurosa', 'verano'],
            self::Temperate => ['templado', 'templada'],
            self::Cold => ['frio', 'fria', 'invierno'],
        };
    }
}
