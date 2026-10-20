<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

enum TagKind: string
{
    case TimeOfDay = 'time_of_day';
    case Climate = 'climate';
    case Occasion = 'occasion';
}
