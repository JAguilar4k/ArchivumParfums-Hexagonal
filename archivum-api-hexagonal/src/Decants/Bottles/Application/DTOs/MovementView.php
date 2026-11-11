<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class MovementView
{
    /** A movement of the history of a bottle; $redeemed says that its line of an order is a decant paid with a pack (R8). */
    public function __construct(public string $id, public string $type, public int $ml, public ?string $reason, public string $date, public ?string $author, public ?string $orderLineId, public bool $redeemed) {}
}
