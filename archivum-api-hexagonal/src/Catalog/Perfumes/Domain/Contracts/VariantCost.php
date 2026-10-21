<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Contracts;

use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\Colones;

interface VariantCost
{
    /** The base cost of a presentation, or null while no supplier has given one. */
    public function costOf(PerfumeVariantId $variant): ?Colones;
}
