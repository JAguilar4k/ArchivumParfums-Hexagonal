<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\DTOs;

final readonly class ShowcaseFilters
{
    public const int DEFAULT_PER_PAGE = 20;

    public const int MAX_PER_PAGE = 100;

    /**
     * Carry the filters of the showcase; they combine, and an absent one filters nothing. The moment and the climate use the
     * values the database stores, and the maximum price is in whole colones.
     */
    public function __construct(
        public ?string $text = null,
        public ?string $family = null,
        public ?string $note = null,
        public ?string $moment = null,
        public ?string $climate = null,
        public ?string $occasion = null,
        public ?int $maxPrice = null,
        public int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {}
}
