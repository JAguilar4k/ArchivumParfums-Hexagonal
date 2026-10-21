<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcaseVariant
{
    /** A presentation that can be sold: its milliliters and its sale price in whole colones. */
    public function __construct(
        public int $ml,
        public int $price,
    ) {}
}
