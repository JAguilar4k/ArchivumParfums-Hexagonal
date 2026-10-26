<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Contracts;

use Src\Decants\Bottles\Domain\Entities\DecantBottle;
use Src\Decants\Bottles\Domain\ValueObjects\DecantBottleId;

interface DecantBottleRepository
{
    public function nextId(): DecantBottleId;

    public function add(DecantBottle $bottle): void;

    public function find(DecantBottleId $id): ?DecantBottle;

    public function updateNotes(DecantBottleId $id, ?string $notes): void;
}
