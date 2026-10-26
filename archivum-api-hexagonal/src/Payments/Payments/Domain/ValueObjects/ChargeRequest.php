<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class ChargeRequest
{
    /** Keep provider payloads outside the port. Amount is always whole colones. */
    public function __construct(
        public string $paymentId,
        public string $businessId,
        public int $amount,
        public PaymentMethod $method = PaymentMethod::Card,
        public ?string $exchangeRate = null,
    ) {}
}
