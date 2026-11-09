<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\DTOs;

final readonly class RecommendedVariant
{
    /** A presentation of a recommended perfume that can be sold: its milliliters and its sale price in whole colones. */
    public function __construct(
        public int $ml,
        public int $price,
    ) {}
}
