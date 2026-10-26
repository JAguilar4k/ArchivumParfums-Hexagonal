<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class BottleView
{
    public function __construct(
        public string $id,
        public string $variantId,
        public string $perfumeId,
        public string $brand,
        public string $perfumeName,
        public int $variantMl,
        public int $totalMl,
        public int $remainingMl,
        public int $reservedMl,
        public string $status,
        public string $openedAt,
        public ?string $notes,
    ) {}
}
