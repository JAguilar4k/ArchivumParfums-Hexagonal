<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface DecantStock
{
    public function reserve(string $bottleId, int $ml, string $lineId, string $userId): void;

    public function release(string $bottleId, int $ml, string $lineId, string $userId): void;

    /** Give back the ml of a line whose order expired unpaid; the system does it, so the movement names no user (R25). */
    public function releaseExpired(string $bottleId, int $ml, string $lineId): void;

    public function consume(string $bottleId, int $ml, string $lineId, string $userId): void;
}
