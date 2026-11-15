<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Infrastructure\Reasons;

use Src\Recommendations\Recommender\Domain\Contracts\RuleReasons;
use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;

/**
 * Writes the reason of a perfume the rules recommend with the templates of lang/es/recommendations.php: one short
 * sentence for each thing the perfume shares with the text, in a fixed order.
 */
final class TranslatedRuleReasons implements RuleReasons
{
    public function write(RuleMatch $match): string
    {
        $sentences = [];

        if ($match->notes !== []) {
            $sentences[] = __('recommendations.rules.notes', ['notes' => $this->list($match->notes)]);
        }

        if ($match->timeOfDay !== null) {
            $sentences[] = __("recommendations.rules.time_of_day.{$match->timeOfDay}");
        }

        if ($match->climate !== null) {
            $sentences[] = __("recommendations.rules.climate.{$match->climate}");
        }

        if ($match->occasion !== null) {
            $sentences[] = __('recommendations.rules.occasion', ['occasion' => $match->occasion]);
        }

        if ($match->fitsBudget) {
            $sentences[] = __('recommendations.rules.budget');
        }

        return implode(' ', $sentences);
    }

    /**
     * The items as a list in Spanish: "vainilla", "vainilla y ámbar", "vainilla, ámbar y haba tonka".
     *
     * @param  non-empty-list<string>  $items
     */
    private function list(array $items): string
    {
        $last = array_pop($items);

        return $items === [] ? $last : implode(', ', $items).' '.__('recommendations.rules.and').' '.$last;
    }
}
