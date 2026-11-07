<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class ReconcileRefundCommand
{
    /** Carry the pending refund the reconciliation asks the provider about and its business. */
    public function __construct(
        public string $refundId,
        public string $businessId,
    ) {}
}
