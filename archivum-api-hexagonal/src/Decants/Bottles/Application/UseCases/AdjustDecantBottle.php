<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\UseCases;

use Src\Decants\Bottles\Application\Contracts\BottleReader;
use Src\Decants\Bottles\Application\DTOs\MovementView;
use Src\Decants\Bottles\Domain\Contracts\MlReservation;
use Src\Decants\Bottles\Domain\Exceptions\BottleNotFoundException;

final readonly class AdjustDecantBottle
{
    public function __construct(private MlReservation $stock, private BottleReader $reader) {}

    public function execute(string $id, int $ml, string $reason, string $userId): MovementView
    {
        $this->reader->find($id) ?? throw BottleNotFoundException::create();

        return $this->reader->movement($this->stock->adjust($id, $ml, $reason, $userId));
    }
}
