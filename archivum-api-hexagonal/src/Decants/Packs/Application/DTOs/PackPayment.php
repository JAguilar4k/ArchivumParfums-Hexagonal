<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

/** The payment of a pack as the purchase answers it: what the customer is charged and where to pay it. */
final readonly class PackPayment
{
    public function __construct(
        public string $id,
        public int $amount,
        public string $currency,
        public int $chargedAmount,
        public string $gateway,
        public string $status,
        public ?string $checkoutUrl,
        public string $method,
        public bool $communicationError,
    ) {}
}
