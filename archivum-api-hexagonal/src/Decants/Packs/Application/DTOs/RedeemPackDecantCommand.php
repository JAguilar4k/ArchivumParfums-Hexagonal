<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class RedeemPackDecantCommand
{
    /**
     * A decant of an order that pays with a pack: its ml and the sale price and ml of the presentation it comes from,
     * which the cap of the pack is compared with (R32).
     */
    public function __construct(
        public string $packId,
        public string $customerId,
        public int $ml,
        public int $salePrice,
        public int $presentationMl,
        public string $redeemedAt,
    ) {}
}
