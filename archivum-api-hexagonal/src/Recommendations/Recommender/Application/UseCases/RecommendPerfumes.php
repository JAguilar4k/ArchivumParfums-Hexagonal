<?php

declare(strict_types=1);

namespace Src\Recommendations\Recommender\Application\UseCases;

use Src\Recommendations\Recommender\Application\Contracts\DiscardedSuggestions;
use Src\Recommendations\Recommender\Application\Contracts\RecommendableCatalog;
use Src\Recommendations\Recommender\Application\DTOs\RecommendationResult;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedPerfume;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedVariant;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\Exceptions\AiUnavailableException;
use Src\Recommendations\Recommender\Domain\Services\PersonalDataMask;
use Src\Recommendations\Recommender\Domain\Services\RuleBasedRecommender;
use Src\Recommendations\Recommender\Domain\Services\SuggestionValidator;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;

final readonly class RecommendPerfumes
{
    /**
     * Receive the showcase of the store, the mask of the contact data, the AI, the check of what it answers with the
     * record of what it discards, and the recommender without AI.
     */
    public function __construct(
        private RecommendableCatalog $catalog,
        private PersonalDataMask $mask,
        private AiRecommender $ai,
        private SuggestionValidator $validator,
        private DiscardedSuggestions $discarded,
        private RuleBasedRecommender $rules,
    ) {}

    /**
     * Recommend up to five perfumes of the showcase of the store of the address for what the customer describes (R48).
     * Only the text, without the emails and phones it may carry, and the catalog reach the AI (R51): this use case does
     * not even know who asks. What the AI suggests is checked against the catalog (R49), and when the AI fails, runs
     * out of time or nothing it suggests is valid, the rules recommender answers instead (R50). Brand, name and prices
     * always come from the catalog.
     */
    public function execute(string $text): RecommendationResult
    {
        $text = $this->mask->apply($text);
        $catalog = $this->catalog->perfumes();
        $recommendation = $this->recommendation($text, $catalog);

        $byId = [];
        foreach ($catalog as $perfume) {
            $byId[$perfume->id] = $perfume;
        }

        $perfumes = [];
        foreach ($recommendation->perfumes as $suggested) {
            // Both recommenders suggest only perfumes of this catalog, so every identifier is found.
            $perfume = $byId[$suggested->perfumeId];
            $perfumes[] = new RecommendedPerfume(
                $perfume->id,
                $perfume->brand,
                $perfume->name,
                $suggested->reason,
                array_map(fn (CatalogVariant $variant): RecommendedVariant => new RecommendedVariant($variant->ml, $variant->price), $perfume->variants),
            );
        }

        $preferences = $recommendation->preferences;

        return new RecommendationResult(
            $recommendation->origin->value,
            $preferences->notes,
            $preferences->timeOfDay,
            $preferences->climate,
            $preferences->occasion,
            $preferences->budget,
            $perfumes,
        );
    }

    /**
     * What the AI suggests once validated, or what the rules recommend when it cannot answer. A store whose showcase is
     * empty has nothing to recommend, so the provider is not asked.
     *
     * @param  list<CatalogPerfume>  $catalog
     */
    private function recommendation(string $text, array $catalog): Recommendation
    {
        if ($catalog === []) {
            return $this->rules->recommend($text, $catalog);
        }

        try {
            $validation = $this->validator->validate($this->ai->recommend($text, $catalog), $catalog);

            if ($validation->discarded !== []) {
                $this->discarded->record($validation->discarded);
            }

            if ($validation->passed()) {
                return $validation->recommendation;
            }
        } catch (AiUnavailableException) {
            // The adapter already left the cause and the duration in the log, without the text (#120).
        }

        return $this->rules->recommend($text, $catalog);
    }
}
