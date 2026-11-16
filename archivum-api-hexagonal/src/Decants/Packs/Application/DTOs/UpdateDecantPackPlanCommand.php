<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class UpdateDecantPackPlanCommand
{
    /**
     * Carry the fields to change; an absent key keeps its current value, and a null cap removes it.
     *
     * @param array{
     *     name?: string,
     *     decants?: int,
     *     ml_per_decant?: int,
     *     price?: int,
     *     validity_days?: int,
     *     max_price_per_ml?: int|null,
     *     active?: bool,
     * } $changes
     */
    public function __construct(
        public string $planId,
        public array $changes,
    ) {}
}
