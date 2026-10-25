<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Domain\Contracts\MlReservation;

final readonly class ReleaseDecantMl
{
    public function __construct(private MlReservation $stock) {}

    public function execute(string $bottleId, int $ml, ?string $orderLineId = null, ?string $userId = null): void
    {
        $this->stock->release($bottleId, $ml, $orderLineId, $userId);
    }
}
