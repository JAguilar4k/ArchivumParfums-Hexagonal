<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\Contracts;

interface DecantStock
{
    public function reserve(string $bottleId, int $ml, string $lineId, string $userId): void;

    public function release(string $bottleId, int $ml, string $lineId, string $userId): void;

    public function consume(string $bottleId, int $ml, string $lineId, string $userId): void;
}
