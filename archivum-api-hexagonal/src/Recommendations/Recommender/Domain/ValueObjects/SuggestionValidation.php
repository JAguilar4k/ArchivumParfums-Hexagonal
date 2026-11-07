<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\ValueObjects;

final readonly class SuggestionValidation
{
    /**
     * What the validation kept of a suggestion and what it discarded (R49).
     *
     * @param  Recommendation  $recommendation  The suggestion without what was discarded.
     * @param  list<DiscardCause>  $discarded  One cause for each value or perfume discarded, in the order they were found.
     */
    public function __construct(
        public Recommendation $recommendation,
        public array $discarded,
    ) {}

    /**
     * Whether the suggestion validates: at least one of its perfumes is left. A suggestion without a valid perfume
     * does not validate, and the rules recommender answers instead (R50).
     */
    public function passed(): bool
    {
        return $this->recommendation->perfumes !== [];
    }
}
