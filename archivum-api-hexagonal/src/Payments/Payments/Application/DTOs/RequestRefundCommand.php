<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class RequestRefundCommand
{
    /** Carry the registered refund to send to the provider and its business. */
    public function __construct(
        public string $refundId,
        public string $businessId,
    ) {}
}
