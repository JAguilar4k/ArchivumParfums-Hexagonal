<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

/** What payments need to know about a locked decant pack: its stored status, the price of its offer and its gateway. */
final readonly class PayablePack
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $status,
        public int $price,
        public string $gateway,
        public ?string $exchangeRate,
    ) {}
}
