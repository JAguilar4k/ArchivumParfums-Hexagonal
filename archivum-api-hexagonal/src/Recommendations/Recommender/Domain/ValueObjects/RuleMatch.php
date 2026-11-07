<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class RuleMatch
{
    /** The points of each note the perfume shares with the text: the notes are what the customer describes most precisely. */
    public const int NOTE_POINTS = 2;

    /** The points of the time of day, the climate, the occasion and the budget, each. */
    public const int TAG_POINTS = 1;

    /**
     * What a perfume of the showcase shares with the customer's text, for the rules recommender (R50).
     *
     * @param  string  $perfumeId  The identifier of the perfume in the catalog.
     * @param  list<string>  $notes  The notes of the perfume that the text names, as the catalog writes them.
     * @param  string|null  $timeOfDay  The time of day of the text, when the perfume is tagged with it.
     * @param  string|null  $climate  The climate of the text, when the perfume is tagged with it.
     * @param  string|null  $occasion  The occasion of the text, when the perfume is tagged with it.
     * @param  bool  $fitsBudget  Whether the text gives a budget and a presentation of the perfume fits it.
     */
    public function __construct(
        public string $perfumeId,
        public array $notes,
        public ?string $timeOfDay,
        public ?string $climate,
        public ?string $occasion,
        public bool $fitsBudget,
    ) {}

    /** The points of the perfume: zero means it shares nothing with the text. */
    public function score(): int
    {
        return self::NOTE_POINTS * count($this->notes)
            + ($this->timeOfDay !== null ? self::TAG_POINTS : 0)
            + ($this->climate !== null ? self::TAG_POINTS : 0)
            + ($this->occasion !== null ? self::TAG_POINTS : 0)
            + ($this->fitsBudget ? self::TAG_POINTS : 0);
    }
}
