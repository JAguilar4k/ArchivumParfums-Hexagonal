<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

enum Climate: string
{
    case Warm = 'warm';
    case Temperate = 'temperate';
    case Cold = 'cold';
}
