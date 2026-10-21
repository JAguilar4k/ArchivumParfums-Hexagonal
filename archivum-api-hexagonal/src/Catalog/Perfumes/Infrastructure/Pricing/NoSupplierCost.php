<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Infrastructure\Pricing;

use Src\Catalog\Perfumes\Domain\Contracts\VariantCost;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\Colones;

/** Answer no cost until the import of the supplier catalog (#174) and the cheapest cost (#175) exist: for now, a presentation is sold with a manual price. */
final readonly class NoSupplierCost implements VariantCost
{
    public function costOf(PerfumeVariantId $variant): ?Colones
    {
        return null;
    }
}
