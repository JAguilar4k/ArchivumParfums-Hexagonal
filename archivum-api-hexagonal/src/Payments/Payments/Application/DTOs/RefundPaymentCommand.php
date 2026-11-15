<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class RefundPaymentCommand
{
    /** Carry the payment to refund, the colones to return and, for an unavailable line (R31), that line. */
    public function __construct(
        public string $paymentId,
        public int $amount,
        public ?string $orderLineId = null,
    ) {}
}
