<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class ChargeReference
{
    /** Carry the persisted reference and expected amount for an authoritative status query. */
    public function __construct(
        public string $paymentId,
        public string $providerReference,
        public string $currency,
        public int $chargedAmount,
        public ?string $checkoutReference = null,
        public bool $captureAllowed = true,
    ) {}
}
