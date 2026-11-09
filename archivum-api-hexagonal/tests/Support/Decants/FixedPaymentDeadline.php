<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Application\Contracts\PaymentDeadline;

/** The minutes a new pack waits for its payment, as the test chose them; 30 is the default of every store (R33). */
final readonly class FixedPaymentDeadline implements PaymentDeadline
{
    public function __construct(private int $minutes = 30) {}

    public function minutes(): int
    {
        return $this->minutes;
    }
}
