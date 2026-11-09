<?php

declare(strict_types=1);

namespace Tests\Support\Recommendations;

use Src\Recommendations\Recommender\Domain\Contracts\RuleReasons;
use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;

/**
 * The reasons of the rules recommender in the tests, without the framework: it writes what the perfume shares with the
 * text in a fixed form, so a test reads the match from the reason.
 */
final class FakeRuleReasons implements RuleReasons
{
    public function write(RuleMatch $match): string
    {
        $parts = [];

        if ($match->notes !== []) {
            $parts[] = 'notes: '.implode(', ', $match->notes);
        }

        if ($match->timeOfDay !== null) {
            $parts[] = 'time of day: '.$match->timeOfDay;
        }

        if ($match->climate !== null) {
            $parts[] = 'climate: '.$match->climate;
        }

        if ($match->occasion !== null) {
            $parts[] = 'occasion: '.$match->occasion;
        }

        if ($match->fitsBudget) {
            $parts[] = 'budget';
        }

        return implode('; ', $parts);
    }
}
