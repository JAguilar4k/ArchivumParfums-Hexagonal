<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcasePage
{
    /**
     * One page of the showcase with the total that matched the filters.
     *
     * @param  list<ShowcaseItem>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {}
}
