<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Colones;

/** The rule that gives a presentation its sale price. Everything is whole colones: nothing here is a float. */
final class SalePrice
{
    /**
     * With a manual price the sale price is that price; without it, the cost of the supplier plus the margin of the
     * store; and without either, there is no price yet and the presentation cannot be sold.
     */
    public static function calculate(?Colones $cost, Colones $margin, ?Colones $manualPrice): ?Colones
    {
        if ($manualPrice !== null) {
            return $manualPrice;
        }

        return $cost === null ? null : new Colones($cost->amount + $margin->amount);
    }
}
