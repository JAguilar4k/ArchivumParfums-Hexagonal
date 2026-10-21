<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class AddPerfumeVariantCommand
{
    /** Carry the perfume, the milliliters and the optional manual price in colones of a new presentation. */
    public function __construct(
        public string $perfumeId,
        public int $ml,
        public ?int $manualPrice,
    ) {}
}
