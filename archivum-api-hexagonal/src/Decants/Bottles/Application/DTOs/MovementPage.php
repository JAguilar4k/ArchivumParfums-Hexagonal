<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class MovementPage
{
    /** @param list<MovementView> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage) {}
}
