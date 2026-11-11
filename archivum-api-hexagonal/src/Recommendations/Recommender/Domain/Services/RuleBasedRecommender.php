<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Services;

use Src\Recommendations\Recommender\Domain\Contracts\RuleReasons;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\Climate;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\TimeOfDay;

/**
 * The recommender without AI that answers when the AI fails, runs out of time or its suggestion does not validate
 * (R50). It reads the preferences by comparing the text, in lowercase and without accents, with the notes and the tags
 * of the store's showcase, scores each perfume by what it shares with them (RuleMatch) and suggests up to five, the
 * best first. It applies the same criterion as SuggestionValidator: only perfumes of the showcase sent, and only those
 * with a presentation within the budget.
 */
final readonly class RuleBasedRecommender
{
    /** Letters with an accent and their plain form, so "Ámbar" in a text finds the note "ámbar" and the other way round. */
    private const array PLAIN_LETTERS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ];

    public function __construct(private RuleReasons $reasons) {}

    /**
     * @param  list<CatalogPerfume>  $catalog  The perfumes of the store's showcase, the only ones it may recommend.
     */
    public function recommend(string $text, array $catalog): Recommendation
    {
        $preferences = $this->preferences($this->plain($text), $catalog);

        $matches = [];
        foreach ($catalog as $perfume) {
            if ($preferences->budget !== null && ! $perfume->fitsBudget($preferences->budget)) {
                continue;
            }

            $match = $this->match($perfume, $preferences);

            // A perfume that shares nothing with the text has nothing to build its reason from: it is not recommended.
            if ($match->score() > 0) {
                $matches[] = $match;
            }
        }

        // The best first; usort keeps the order of the showcase among perfumes with the same score.
        usort($matches, fn (RuleMatch $first, RuleMatch $second): int => $second->score() <=> $first->score());

        return new Recommendation(
            RecommendationOrigin::Rules,
            $preferences,
            array_map(
                fn (RuleMatch $match): SuggestedPerfume => new SuggestedPerfume($match->perfumeId, $this->reasons->write($match)),
                array_slice($matches, 0, Recommendation::MAX_PERFUMES),
            ),
        );
    }

    /**
     * What the text asks for, among what the showcase has: the notes it names, the time of day and the climate it
     * mentions first, the occasion it mentions first and the budget it gives.
     *
     * @param  list<CatalogPerfume>  $catalog
     */
    private function preferences(string $text, array $catalog): CustomerPreferences
    {
        $notes = [];
        foreach ($catalog as $perfume) {
            foreach ($perfume->notes as $note) {
                $notes[] = $note;
            }
        }

        return new CustomerPreferences(
            $this->mentioned($text, $notes),
            $this->mentionedValue($text, TimeOfDay::cases(), $this->tags($catalog, 'time_of_day')),
            $this->mentionedValue($text, Climate::cases(), $this->tags($catalog, 'climate')),
            $this->mentioned($text, $this->tags($catalog, 'occasion'))[0] ?? null,
            $this->budget($text),
        );
    }

    /** What the perfume shares with the preferences. */
    private function match(CatalogPerfume $perfume, CustomerPreferences $preferences): RuleMatch
    {
        $wantedNotes = array_map($this->plain(...), $preferences->notes);
        $occasions = array_map($this->plain(...), $perfume->tags['occasion']);

        return new RuleMatch(
            $perfume->id,
            array_values(array_filter($perfume->notes, fn (string $note): bool => in_array($this->plain($note), $wantedNotes, true))),
            in_array($preferences->timeOfDay, $perfume->tags['time_of_day'], true) ? $preferences->timeOfDay : null,
            in_array($preferences->climate, $perfume->tags['climate'], true) ? $preferences->climate : null,
            $preferences->occasion !== null && in_array($this->plain($preferences->occasion), $occasions, true) ? $preferences->occasion : null,
            $preferences->budget !== null,
        );
    }

    /**
     * The phrases the text mentions, as the catalog writes them, each once and in the order the text mentions them.
     *
     * @param  list<string>  $phrases
     * @return list<string>
     */
    private function mentioned(string $text, array $phrases): array
    {
        $found = [];
        foreach ($phrases as $phrase) {
            $plain = $this->plain($phrase);
            $position = $this->position($text, $plain);

            if ($position !== null && ! isset($found[$plain])) {
                $found[$plain] = ['phrase' => $phrase, 'position' => $position];
            }
        }

        usort($found, fn (array $first, array $second): int => $first['position'] <=> $second['position']);

        return array_column($found, 'phrase');
    }

    /**
     * The value the text mentions first, by any of its words, among the values the showcase tags its perfumes with.
     *
     * @param  list<TimeOfDay>|list<Climate>  $values
     * @param  list<string>  $tagged  The values of that kind in the tags of the showcase.
     */
    private function mentionedValue(string $text, array $values, array $tagged): ?string
    {
        $first = null;
        $firstPosition = PHP_INT_MAX;

        foreach ($values as $value) {
            if (! in_array($value->value, $tagged, true)) {
                continue;
            }

            foreach ($value->words() as $word) {
                $position = $this->position($text, $word);

                if ($position !== null && $position < $firstPosition) {
                    $first = $value->value;
                    $firstPosition = $position;
                }
            }
        }

        return $first;
    }

    /**
     * The budget the text gives: the largest amount of money it names, or null when it names none. An amount is a
     * number with ₡ before it, with "colones", "mil" or "k" after it, written with thousands separators or of at least
     * five digits. A number of ml is not money, and neither is a short number such as an age or a year.
     */
    private function budget(string $text): ?int
    {
        preg_match_all(
            '/(₡\s*)?(\d{1,3}(?:[ .,]\d{3})+|\d+)(\s*(?:mil|k)(?![\p{L}\p{N}]))?(\s*(?:colones|ml|mililitros?)(?![\p{L}\p{N}]))?/u',
            $text,
            $amounts,
            PREG_SET_ORDER,
        );

        $budget = null;
        foreach ($amounts as $amount) {
            $unit = trim($amount[4] ?? '');

            if ($unit !== '' && $unit !== 'colones') {
                continue;
            }

            $digits = (string) preg_replace('/\D/', '', $amount[2]);
            $thousands = trim($amount[3] ?? '') !== '';
            $colones = (int) $digits * ($thousands ? 1000 : 1);
            $isMoney = trim($amount[1]) !== '' || $thousands || $unit === 'colones' || $digits !== $amount[2] || strlen($digits) >= 5;

            if ($isMoney && $colones > 0 && ($budget === null || $colones > $budget)) {
                $budget = $colones;
            }
        }

        return $budget;
    }

    /**
     * The values of one kind of tag across the showcase.
     *
     * @param  list<CatalogPerfume>  $catalog
     * @param  'time_of_day'|'climate'|'occasion'  $kind
     * @return list<string>
     */
    private function tags(array $catalog, string $kind): array
    {
        $tags = [];
        foreach ($catalog as $perfume) {
            foreach ($perfume->tags[$kind] as $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /** Where the plain text first mentions the plain phrase as whole words, also in plural, or null when it does not. */
    private function position(string $text, string $phrase): ?int
    {
        if ($phrase === '') {
            return null;
        }

        if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?:e?s)?(?![\p{L}\p{N}])/u', $text, $found, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        return $found[0][1];
    }

    /** The text in lowercase, without accents and with single spaces, the form in which texts, notes and tags are compared. */
    private function plain(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strtr(mb_strtolower($text), self::PLAIN_LETTERS)));
    }
}
