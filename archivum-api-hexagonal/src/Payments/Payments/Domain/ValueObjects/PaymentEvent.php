<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;

/**
 * What a verified notice of a provider says, as its gateway adapter reads it: the event, the provider's reference of
 * the charge it is about, the identifiers Archivum sent with that charge when the notice brings them back, and the
 * amount in the unit the payment keeps (whole colones with ONVO, cents of a dollar with PayPal). A notice that is not
 * about a charge, such as a subscription renewal, carries only its identity.
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
    ) {
        if (! in_array($gateway, ['onvo', 'paypal'], true) || ! $this->fits($eventId) || ! $this->fits($type)
            || ($reference !== null && ! $this->fits($reference))) {
            throw new InvalidValueException('payments.invalid_webhook');
        }
    }

    /** Only a notice that names a charge of the provider can change a payment. */
    public function aboutCharge(): bool
    {
        return $this->reference !== null;
    }

    /** Whether an identifier of the provider fits the columns that keep it. */
    private function fits(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255;
    }
}
