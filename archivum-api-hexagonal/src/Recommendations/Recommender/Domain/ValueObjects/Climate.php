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
}
