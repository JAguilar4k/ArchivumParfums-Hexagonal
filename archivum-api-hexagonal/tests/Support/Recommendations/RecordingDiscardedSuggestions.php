<?php

declare(strict_types=1);

namespace Tests\Support\Recommendations;

use Src\Recommendations\Recommender\Application\Contracts\DiscardedSuggestions;
use Src\Recommendations\Recommender\Domain\ValueObjects\DiscardCause;

/** The record of discarded suggestions of the tests: it keeps the causes of each record in memory. */
final class RecordingDiscardedSuggestions implements DiscardedSuggestions
{
    /** @var list<list<DiscardCause>> */
    public private(set) array $recorded = [];

    public function record(array $causes): void
    {
        $this->recorded[] = $causes;
    }
}
