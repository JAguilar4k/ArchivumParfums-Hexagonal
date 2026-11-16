<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

/** An offer on sale as a visitor of the store sees it, read without the domain like the showcase. */
final readonly class PackPlanOnSale
{
    public function __construct(
        public string $id,
        public string $name,
        public int $decants,
        public int $mlPerDecant,
        public int $price,
        public int $validityDays,
        public ?int $maxPricePerMl,
    ) {}
}
