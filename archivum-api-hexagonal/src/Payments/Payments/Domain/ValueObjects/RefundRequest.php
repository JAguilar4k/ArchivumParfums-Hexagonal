<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class RefundRequest
{
    /** Describe a refund with local identities and an amount in whole colones. */
    public function __construct(
        public string $refundId,
        public string $providerReference,
        public int $amount,
        public ?string $captureReference = null,
        public ?string $exchangeRate = null,
        public ?string $providerRefundReference = null,
    ) {}
}
