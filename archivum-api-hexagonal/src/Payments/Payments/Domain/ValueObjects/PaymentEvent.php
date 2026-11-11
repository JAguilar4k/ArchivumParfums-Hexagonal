<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

/**
 * What a verified notice of a provider says, as its gateway adapter reads it: the event, the provider's reference of
 * the charge it is about, the identifiers Archivum sent with that charge when the notice brings them back, and the
 * amount in the unit the payment keeps (whole colones with ONVO, cents of a dollar with PayPal). A notice that reports
 * a failed charge may come without its amount, as ONVO sends it. A notice about a refund (#97) also carries the
 * provider's reference of the refund and the refund Archivum sent with it. A notice that is about neither, such as a
 * subscription renewal, carries only its identity.
 */
final readonly class PaymentEvent
{
    public function __construct(
        public string $gateway,
        public string $eventId,
        public string $type,
        public ?string $reference = null,
        public ?BusinessId $businessId = null,
        public ?PaymentId $paymentId = null,
        public ?string $currency = null,
        public ?int $amount = null,
        public ?string $refundReference = null,
        public ?RefundId $refundId = null,
        public bool $reportsFailure = false,
    ) {
        if (! in_array($gateway, ['onvo', 'paypal'], true) || ! $this->fits($eventId) || ! $this->fits($type)
            || ($reference !== null && ! $this->fits($reference))
            || ($refundReference !== null && ! $this->fits($refundReference))) {
            throw new InvalidValueException('payments.invalid_webhook');
        }
    }

    /** Only a notice that names a charge of the provider, and not one of its refunds, can settle a payment. */
    public function aboutCharge(): bool
    {
        return $this->reference !== null && $this->refundReference === null;
    }

    /** A notice that names a refund of the provider can only settle that refund (#97). */
    public function aboutRefund(): bool
    {
        return $this->refundReference !== null;
    }

    /** Whether an identifier of the provider fits the columns that keep it. */
    private function fits(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255;
    }
}
