<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class PaymentReference
{
    /** Point to a payment and its business. */
    public function __construct(
        public string $paymentId,
        public string $businessId,
    ) {}
}
