<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Domain\Services;

use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\Climate;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\DiscardCause;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestionValidation;
use Src\Recommendations\Recommender\Domain\ValueObjects\TimeOfDay;

/**
 * Checks what the AI suggests against the catalog sent to it, before any of it is used (R49). The suggestion is
 * untrusted data, never instructions: it is compared in memory with the catalog and none of it reaches a query. What
 * is valid stays and the rest is discarded, value by value and perfume by perfume. Brand, name and prices are not
 * part of a suggestion, so they can only come from the catalog.
 */
final readonly class SuggestionValidator
{
    /** The longest reason a suggested perfume may carry, in characters: the specification asks for a short one (R48). */
    public const int MAX_REASON_LENGTH = 200;

    /**
     * @param  list<CatalogPerfume>  $catalog  The catalog sent to the AI: the perfumes of the store's showcase, all of them active.
     */
    public function validate(Recommendation $suggestion, array $catalog): SuggestionValidation
    {
        $discarded = [];
        $preferences = $suggestion->preferences;

        $timeOfDay = $preferences->timeOfDay;
        if ($timeOfDay !== null && TimeOfDay::tryFrom($timeOfDay) === null) {
            $timeOfDay = null;
            $discarded[] = DiscardCause::InvalidTimeOfDay;
        }

        $climate = $preferences->climate;
        if ($climate !== null && Climate::tryFrom($climate) === null) {
            $climate = null;
            $discarded[] = DiscardCause::InvalidClimate;
        }

        $budget = $preferences->budget;
        if ($budget !== null && $budget < 1) {
            $budget = null;
            $discarded[] = DiscardCause::InvalidBudget;
        }

        $byId = [];
        foreach ($catalog as $perfume) {
            $byId[$perfume->id] = $perfume;
        }

        $kept = [];
        foreach ($suggestion->perfumes as $suggested) {
            $cause = $this->discardCause($suggested, $byId, $kept, $budget);

            if ($cause !== null) {
                $discarded[] = $cause;

                continue;
            }

            $kept[$suggested->perfumeId] = new SuggestedPerfume($suggested->perfumeId, mb_trim($suggested->reason));
        }

        return new SuggestionValidation(
            new Recommendation(
                $suggestion->origin,
                new CustomerPreferences($preferences->notes, $timeOfDay, $climate, $preferences->occasion, $budget),
                array_values($kept),
            ),
            $discarded,
        );
    }

    /**
     * Why a suggested perfume is discarded, or null when it stays. When several causes apply, the first check that
     * fails names it.
     *
     * @param  array<string, CatalogPerfume>  $catalog  The catalog sent, by identifier.
     * @param  array<string, SuggestedPerfume>  $kept  The perfumes that already stay, by identifier.
     * @param  int|null  $budget  The valid budget, or null when the customer gave none.
     */
    private function discardCause(SuggestedPerfume $suggested, array $catalog, array $kept, ?int $budget): ?DiscardCause
    {
        $perfume = $catalog[$suggested->perfumeId] ?? null;
        $reason = mb_trim($suggested->reason);

        if ($perfume === null) {
            return DiscardCause::NotInCatalog;
        }

        if (isset($kept[$suggested->perfumeId])) {
            return DiscardCause::Repeated;
        }

        if ($budget !== null && ! $perfume->fitsBudget($budget)) {
            return DiscardCause::OverBudget;
        }

        if ($reason === '') {
            return DiscardCause::EmptyReason;
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return DiscardCause::ReasonTooLong;
        }

        return null;
    }
}
