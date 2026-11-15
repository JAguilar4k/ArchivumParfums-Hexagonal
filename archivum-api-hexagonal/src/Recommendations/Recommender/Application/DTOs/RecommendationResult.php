<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\DTOs;

final readonly class RecommendationResult
{
    /**
     * What the recommender answers to a customer (R48): who produced it, the preferences read from the text and up to
     * five perfumes, the best first. What the text does not mention is null or an empty list.
     *
     * @param  string  $origin  ai or rules (R50).
     * @param  list<string>  $notes  The notes the text names.
     * @param  string|null  $timeOfDay  day, afternoon or night, as the catalog tags it.
     * @param  string|null  $climate  warm, temperate or cold, as the catalog tags it.
     * @param  int|null  $budget  The most the customer wants to pay, in whole colones.
     * @param  list<RecommendedPerfume>  $perfumes
     */
    public function __construct(
        public string $origin,
        public array $notes,
        public ?string $timeOfDay,
        public ?string $climate,
        public ?string $occasion,
        public ?int $budget,
        public array $perfumes,
    ) {}
}
