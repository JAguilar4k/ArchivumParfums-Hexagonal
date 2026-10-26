<?php

declare(strict_types=1);

namespace Src\Sales\Orders\Application\DTOs;

final readonly class PlaceOrderCommand
{
    /** @param list<array{kind:string, reference:string, ml:?int, quantity:int}> $lines */
    public function __construct(public string $userId, public array $lines, public string $placedAt) {}
}
