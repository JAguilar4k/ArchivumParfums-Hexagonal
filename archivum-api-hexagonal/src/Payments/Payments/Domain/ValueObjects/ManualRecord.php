<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Domain\ValueObjects;

use DateTimeImmutable;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\MemberId;

/** What an owner records about a payment received on delivery (R29): who, how the customer paid, its reference and when. */
final readonly class ManualRecord
{
    /** Longest reference that payments.manual_reference keeps. */
    public const int MAX_REFERENCE_LENGTH = 255;

    /** The reference names the SINPE transfer or the cash receipt, so it cannot be blank. */
    public function __construct(
        public MemberId $recordedBy,
        public ManualPaymentMethod $method,
        public string $reference,
        public DateTimeImmutable $recordedAt,
    ) {
        if (trim($reference) === '' || mb_strlen($reference) > self::MAX_REFERENCE_LENGTH) {
            throw new InvalidValueException('payments.invalid_manual_reference');
        }
    }
}
