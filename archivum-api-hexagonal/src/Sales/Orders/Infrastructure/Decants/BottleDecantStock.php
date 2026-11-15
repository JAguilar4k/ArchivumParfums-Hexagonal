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

    /** The movement keeps the line and the reason, in the words of lang/es, and no user: the system released it. */
    public function releaseExpired(string $bottleId, int $ml, string $lineId): void
    {
        $this->reservations->release($bottleId, $ml, $lineId, null, (string) __('orders.expired_release'));
    }

    public function consume(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->reservations->consume($bottleId, $ml, $lineId, $userId);
    }
}
