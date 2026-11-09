<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class CreateDecantPackPlanCommand
{
    /** Carry the data of a new offer with the vocabulary of the domain; the business is not part of it, it comes from the session. */
    public function __construct(
        public string $name,
        public int $decants,
        public int $mlPerDecant,
        public int $price,
        public int $validityDays,
        public ?int $maxPricePerMl,
        public bool $isActive,
    ) {}
}
