<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

use Src\Payments\Payments\Domain\ValueObjects\PaymentPurpose;

/** What an order or a decant pack owes now, as the server keeps it: the purpose and amount of the charge and its gateway. */
final readonly class PayableCharge
{
    public function __construct(
        public string $businessId,
        public PaymentPurpose $purpose,
        public int $amount,
        public string $gateway,
        public ?string $exchangeRate,
    ) {}
}
