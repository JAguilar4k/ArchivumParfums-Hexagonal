<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

use Src\Catalog\Perfumes\Domain\Entities\Perfume;

final readonly class PerfumePage
{
    /**
     * Carry one page of perfumes with the total that matched the search.
     *
     * @param  list<Perfume>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
