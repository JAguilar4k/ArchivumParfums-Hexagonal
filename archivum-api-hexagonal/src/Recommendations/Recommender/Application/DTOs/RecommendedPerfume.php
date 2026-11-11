<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\DTOs;

final readonly class RecommendedPerfume
{
    /**
     * A recommended perfume as the catalog of the store has it, with the reason of the recommender. Brand, name and
     * presentations always come from the catalog, never from what the AI answered (R49).
     *
     * @param  list<RecommendedVariant>  $variants  The presentations that can be sold.
     */
    public function __construct(
        public string $id,
        public string $brand,
        public string $name,
        public string $reason,
        public array $variants,
    ) {}
}
