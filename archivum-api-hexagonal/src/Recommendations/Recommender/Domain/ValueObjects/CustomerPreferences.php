<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class CustomerPreferences
{
    /**
     * What the customer's text asks for (R48). What the text does not mention is null or an empty list. The values
     * are what the recommender read and are not checked yet: a moment, a climate or a budget that does not exist is
     * discarded before the recommendation is used (#119).
     *
     * @param  list<string>  $notes  The notes the text names.
     * @param  string|null  $timeOfDay  day, afternoon or night, as the catalog tags it.
     * @param  string|null  $climate  warm, temperate or cold, as the catalog tags it.
     * @param  int|null  $budget  The most the customer wants to pay, in whole colones.
     */
    public function __construct(
        public array $notes,
        public ?string $timeOfDay,
        public ?string $climate,
        public ?string $occasion,
        public ?int $budget,
    ) {}
}
