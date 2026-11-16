<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class ReturnPackDecantCommand
{
    /** The line of a cancelled or expired order whose redeemed decant goes back to its pack. */
    public function __construct(
        public string $packId,
        public string $orderLineId,
        public string $returnedAt,
    ) {}
}
