<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Application\DTOs;

final readonly class ListPerfumesQuery
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    /** Carry the text to look for in the brand and the name, and the page asked for. */
    public function __construct(
        public ?string $text = null,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}
}
