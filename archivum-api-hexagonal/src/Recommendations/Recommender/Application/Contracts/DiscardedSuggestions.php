<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\Contracts;

use Src\Recommendations\Recommender\Domain\ValueObjects\DiscardCause;

interface DiscardedSuggestions
{
    /**
     * Leave a record of what the validation discarded from a suggestion of the AI (#119): how many values and perfumes
     * and why, never the customer's text nor the suggestion itself (R51).
     *
     * @param  non-empty-list<DiscardCause>  $causes  One cause for each value or perfume discarded.
     */
    public function record(array $causes): void;
}
