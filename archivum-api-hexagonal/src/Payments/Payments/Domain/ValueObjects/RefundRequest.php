<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

final readonly class RefundRequest
{
    /**
     * Describe a refund with the identities Archivum keeps: the refund and its business, the provider's references of
     * the original charge and, once the provider accepted it, of the refund itself. The amount is in the unit the
     * provider charged the payment (whole colones with ONVO, cents of a dollar with PayPal).
     */
    public function __construct(
        public string $refundId,
        public string $businessId,
        public string $providerReference,
        public int $chargedAmount,
        public ?string $captureReference = null,
        public ?string $providerRefundReference = null,
    ) {}
}
