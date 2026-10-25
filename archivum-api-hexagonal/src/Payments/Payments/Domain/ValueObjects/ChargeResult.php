<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class ChargeResult
{
    /** Amount uses whole colones for CRC and cents for USD, as persisted in payments. */
    public function __construct(
        public string $providerReference,
        public PaymentStatus $status,
        public string $currency,
        public int $chargedAmount,
        public ?string $checkoutUrl = null,
        public ?string $checkoutReference = null,
        public ?string $captureReference = null,
    ) {}
}
