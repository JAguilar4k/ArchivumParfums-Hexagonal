<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

use LogicException;
use Src\Payments\Payments\Domain\Entities\Payment;

final readonly class ManualPaymentResult
{
    /** Carry a payment received on delivery as the panel shows it, with the owner who recorded it. */
    public function __construct(
        public string $id,
        public string $purpose,
        public int $amount,
        public string $status,
        public string $method,
        public string $reference,
        public string $recordedById,
        public string $recordedByName,
        public string $recordedAt,
    ) {}

    /** Describe a payment recorded by hand; the name of the owner does not live in the payment. */
    public static function from(Payment $payment, PaymentRecorder $recorder): self
    {
        $record = $payment->manual ?? throw new LogicException('Only a payment received on delivery has a manual record.');

        return new self(
            $payment->id->value,
            $payment->purpose->value,
            $payment->amount->amount,
            $payment->status->value,
            $record->method->value,
            $record->reference,
            $record->recordedBy->value,
            $recorder->name,
            $record->recordedAt->format(DATE_ATOM),
        );
    }
}
