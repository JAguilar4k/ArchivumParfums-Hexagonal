<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class RecordManualPaymentCommand
{
    /**
     * Carry the order of the URL, the account of the token and what the owner received on delivery: the method
     * (`sinpe` or `cash`), the amount in colones and the reference of the transfer or the receipt.
     */
    public function __construct(
        public string $orderId,
        public string $userId,
        public string $method,
        public int $amount,
        public string $reference,
        public string $recordedAt,
    ) {}
}
