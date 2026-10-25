<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Application\DTOs;

final readonly class PayableOrder
{
    public function __construct(
        public string $id,
        public string $businessId,
        public string $status,
        public int $dueNow,
        public int $subtotal,
        public int $paid,
        public bool $hasBottles,
        public string $gateway,
        public ?string $exchangeRate,
        public bool $refundPending = false,
    ) {}
}
