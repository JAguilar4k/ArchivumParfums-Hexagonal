<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

use Src\Payments\Payments\Domain\Entities\Payment;

final readonly class PaymentResult
{
    public function __construct(
        public string $id,
        public string $purpose,
        public int $amount,
        public string $currency,
        public int $chargedAmount,
        public string $gateway,
        public string $status,
        public ?string $checkoutUrl,
        public string $method,
        public bool $created,
        public bool $communicationError,
    ) {}

    public static function from(Payment $payment, bool $created = false, bool $communicationError = false): self
    {
        return new self($payment->id->value, $payment->purpose->value, $payment->amount->amount, $payment->chargedCurrency, $payment->chargedAmount, $payment->gateway, $payment->status->value, $payment->checkoutUrl, $payment->method->value, $created, $communicationError);
    }
}
