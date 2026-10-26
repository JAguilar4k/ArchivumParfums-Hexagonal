<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Contracts;

interface MlReservation
{
    public function reserve(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void;

    public function release(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void;

    public function consume(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void;

    public function adjust(string $bottleId, int $ml, string $reason, string $userId): string;
}
