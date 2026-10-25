<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\Entities;

use Src\Shared\Domain\Exceptions\InvalidValueException;

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
    ) {
        if ($amount <= 0 || ! in_array($status, ['pending', 'succeeded', 'failed'], true)) {
            throw new InvalidValueException('payments.invalid_refund');
        }
    }

    public static function create(string $id, string $businessId, string $paymentId, int $amount, ?string $orderLineId = null): self
    {
        return new self($id, $businessId, $paymentId, $amount, $orderLineId, 'pending', null, false);
    }

    public static function reconstitute(string $id, string $businessId, string $paymentId, int $amount, ?string $orderLineId, string $status, ?string $providerReference, bool $attempted): self
    {
        return new self($id, $businessId, $paymentId, $amount, $orderLineId, $status, $providerReference, $attempted);
    }

    public function start(): void
    {
        $this->attempted = true;
    }

    public function record(string $reference, string $status): void
    {
        if (! in_array($status, ['pending', 'succeeded', 'failed'], true) || $this->status !== 'pending') {
            throw new InvalidValueException('payments.invalid_refund');
        }
        $this->providerReference = $reference;
        $this->status = $status;
    }
}
