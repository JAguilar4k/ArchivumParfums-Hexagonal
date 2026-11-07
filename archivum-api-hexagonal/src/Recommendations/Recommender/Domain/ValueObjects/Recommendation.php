<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class Recommendation
{
    /**
     * The preferences read from the customer's text and the perfumes suggested for them, best first, with who
     * produced them (R48, R50).
     *
     * @param  list<SuggestedPerfume>  $perfumes
     */
    public function __construct(
        public RecommendationOrigin $origin,
        public CustomerPreferences $preferences,
        public array $perfumes,
    ) {}
}
