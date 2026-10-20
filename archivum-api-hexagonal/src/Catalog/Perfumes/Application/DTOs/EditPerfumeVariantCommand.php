<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class EditPerfumeVariantCommand
{
    /**
     * Carry the fields to change; an absent key keeps its current value, and a null manual price removes it.
     *
     * @param  array{ml?: int, manual_price?: int|null, active?: bool}  $changes
     */
    public function __construct(
        public string $perfumeId,
        public string $variantId,
        public array $changes,
    ) {}
}
