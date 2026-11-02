<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class SuggestedPerfume
{
    /**
     * A perfume the recommender suggests, by the identifier it has in the catalog, with a short reason in Spanish.
     * Until it is checked against the catalog (#119), the identifier is only what the recommender answered.
     */
    public function __construct(
        public string $perfumeId,
        public string $reason,
    ) {}
}
