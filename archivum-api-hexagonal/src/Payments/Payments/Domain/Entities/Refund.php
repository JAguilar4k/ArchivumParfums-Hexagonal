<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Entities;

use Src\Shared\Domain\Exceptions\InvalidValueException;

/**
 * A refund of one payment (R40). It is part of the payment it returns: Payment::requestRefund() creates it, and every
 * operation that changes it locks its payment first. The amount is in whole colones, as the payment keeps it; the
 * charged amount is the same refund in the unit the provider charged the payment (whole colones with ONVO, cents of a
 * dollar with PayPal), fixed when the request leaves for the provider and checked against what the provider answers.
 */
final class Refund
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $paymentId,
        public readonly int $amount,
        public readonly ?string $orderLineId,
        public private(set) string $status,
        public private(set) ?string $providerReference,
        public private(set) bool $attempted,
        public private(set) ?int $chargedAmount,
    ) {
        if ($amount <= 0 || ! in_array($status, ['pending', 'succeeded', 'failed'], true)
            || ($chargedAmount !== null && $chargedAmount <= 0) || ($attempted && $chargedAmount === null)) {
            throw new InvalidValueException('payments.invalid_refund');
        }
    }

    /** A new refund is pending and has not left for the provider yet. */
    public static function create(string $id, string $businessId, string $paymentId, int $amount, ?string $orderLineId = null): self
    {
        return new self($id, $businessId, $paymentId, $amount, $orderLineId, 'pending', null, false, null);
    }

    public static function reconstitute(string $id, string $businessId, string $paymentId, int $amount, ?string $orderLineId, string $status, ?string $providerReference, bool $attempted, ?int $chargedAmount): self
    {
        return new self($id, $businessId, $paymentId, $amount, $orderLineId, $status, $providerReference, $attempted, $chargedAmount);
    }

    /**
     * Record that the request leaves for the provider with this charged amount: a refund leaves once, and without the
     * provider's answer it is never sent again.
     */
    public function start(int $chargedAmount): void
    {
        if ($this->status !== 'pending' || $this->attempted || $chargedAmount <= 0) {
            throw new InvalidValueException('payments.invalid_refund');
        }
        $this->attempted = true;
        $this->chargedAmount = $chargedAmount;
    }

    /**
     * Keep what the provider answers about a pending refund: its reference and its state. The reference never changes
     * once it is known, and a refund that is no longer pending does not change. Only a refusal comes without a
     * reference, because the provider created no refund.
     */
    public function record(?string $reference, string $status): void
    {
        if (! in_array($status, ['pending', 'succeeded', 'failed'], true) || $this->status !== 'pending'
            || ($reference === null && $status !== 'failed')
            || ($reference !== null && $this->providerReference !== null && $this->providerReference !== $reference)) {
            throw new InvalidValueException('payments.invalid_refund');
        }
        $this->providerReference = $reference ?? $this->providerReference;
        $this->status = $status;
    }
}
