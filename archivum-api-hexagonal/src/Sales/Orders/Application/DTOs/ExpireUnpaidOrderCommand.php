<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\DTOs;

final readonly class ExpireUnpaidOrderCommand
{
    /** Carry the order whose deadline arrived and the moment it is checked. */
    public function __construct(
        public string $orderId,
        public string $checkedAt,
    ) {}
}
