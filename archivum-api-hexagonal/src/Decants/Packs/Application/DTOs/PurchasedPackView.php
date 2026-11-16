<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

/**
 * A pack as the customer who bought it reads it, with its store and its offer, read without the domain. The status is
 * the one of the pack at the moment of the reading, and the dates are ISO 8601 with their offset.
 */
final readonly class PurchasedPackView
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $businessName,
        public string $businessSlug,
        public string $planId,
        public string $planName,
        public int $totalDecants,
        public int $remainingDecants,
        public int $mlPerDecant,
        public string $status,
        public string $expiresAt,
        public string $boughtAt,
    ) {}
}
