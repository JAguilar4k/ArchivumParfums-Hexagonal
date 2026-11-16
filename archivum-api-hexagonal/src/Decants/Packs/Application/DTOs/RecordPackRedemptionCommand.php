<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class RecordPackRedemptionCommand
{
    /** The saved line of an order that redeemed a decant of the pack. */
    public function __construct(
        public string $packId,
        public string $orderLineId,
    ) {}
}
