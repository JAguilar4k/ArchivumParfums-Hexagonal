<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class SuggestedPerfume
{
    /**
     * A perfume the recommender suggests, by the identifier it has in the catalog, with a short reason in Spanish.
     * In what the AI answers, the identifier is only what it answered until SuggestionValidator finds it in the catalog.
     */
    public function __construct(
        public string $perfumeId,
        public string $reason,
    ) {}
}
