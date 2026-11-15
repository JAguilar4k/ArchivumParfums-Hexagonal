<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class ReconcilePaymentCommand
{
    /** Carry the pending payment the reconciliation asks the provider about and its business. */
    public function __construct(
        public string $paymentId,
        public string $businessId,
    ) {}
}
