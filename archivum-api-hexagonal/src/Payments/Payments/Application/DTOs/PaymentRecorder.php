<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class PaymentRecorder
{
    /** Carry the member of the staff who records a payment on delivery and the name the panel shows for them. */
    public function __construct(
        public string $memberId,
        public string $name,
    ) {}
}
