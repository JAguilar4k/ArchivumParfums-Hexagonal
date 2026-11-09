<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Application\DTOs;

final readonly class SoldPackPage
{
    /** @param  list<SoldPackView>  $items */
    public function __construct(public array $items, public int $total, public int $page, public int $perPage) {}
}
