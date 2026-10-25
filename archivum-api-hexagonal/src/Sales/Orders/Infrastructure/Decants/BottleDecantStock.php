<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Infrastructure\Decants;

use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Sales\Orders\Application\Contracts\DecantStock;

final readonly class BottleDecantStock implements DecantStock
{
    public function __construct(private MlReservation $reservations) {}

    public function reserve(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->reservations->reserve($bottleId, $ml, $lineId, $userId);
    }

    public function release(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->reservations->release($bottleId, $ml, $lineId, $userId);
    }

    public function consume(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->reservations->consume($bottleId, $ml, $lineId, $userId);
    }
}
