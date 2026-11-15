<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

/**
 * A pack sold in the store as its owner reads it, read without the domain: of the customer, only the name (#110). The
 * status is the one of the pack at the moment of the reading, and the expiry is ISO 8601 with its offset.
 */
final readonly class SoldPackView
{
    public function __construct(
        public string $id,
        public string $customerName,
        public string $planId,
        public string $planName,
        public int $totalDecants,
        public int $remainingDecants,
        public int $mlPerDecant,
        public string $status,
        public string $expiresAt,
    ) {}
}
