<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class MovementView
{
    public function __construct(public string $id, public string $type, public int $ml, public ?string $reason, public string $date, public ?string $author, public ?string $orderLineId) {}
}
