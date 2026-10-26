<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Application\DTOs;

final readonly class BottlePage
{
    /** @param list<BottleView> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage) {}
}
