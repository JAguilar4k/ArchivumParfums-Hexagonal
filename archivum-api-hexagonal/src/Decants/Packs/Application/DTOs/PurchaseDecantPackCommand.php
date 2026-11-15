<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class PurchaseDecantPackCommand
{
    /**
     * Carry the purchase of an offer by the account of the session; the store is not part of it, it comes from the
     * address, and the amount is never part of it either.
     *
     * @param  string  $method  card or sinpe_mobile
     */
    public function __construct(
        public string $planId,
        public string $userId,
        public ?string $idempotencyKey,
        public string $method,
        public string $purchasedAt,
    ) {}
}
