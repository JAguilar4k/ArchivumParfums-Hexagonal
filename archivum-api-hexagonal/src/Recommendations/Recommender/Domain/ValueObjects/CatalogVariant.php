<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class CatalogVariant
{
    /** A presentation of a catalog perfume that can be sold: its milliliters and its sale price in whole colones. */
    public function __construct(
        public int $ml,
        public int $price,
    ) {}
}
