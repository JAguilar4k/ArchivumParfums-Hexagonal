<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class RefundReference
{
    /** Point to a refund and its business. */
    public function __construct(
        public string $refundId,
        public string $businessId,
    ) {}
}
