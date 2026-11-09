<?php

declare(strict_types=1);

namespace Tests\Support\Recommendations;

use Closure;
use Src\Recommendations\Recommender\Domain\Contracts\AiRecommender;
use Src\Recommendations\Recommender\Domain\Exceptions\AiUnavailableException;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;

/**
 * The AI recommender of the tests. It answers what the test fixes, fails, or runs out of time at once, without a
 * provider and without waiting the real limit, and it remembers what it received.
 */
final class FakeAiRecommender implements AiRecommender
{
    /** An identifier no catalog has. */
    public const string UNKNOWN_PERFUME = '019a0000-0000-7000-8000-000000000000';

    /** @var list<array{text: string, catalog: list<CatalogPerfume>}> */
    public private(set) array $received = [];

    /** @param  Closure(string, list<CatalogPerfume>): Recommendation  $answer */
    private function __construct(private readonly Closure $answer) {}

    /** Answer the same recommendation every time. */
    public static function answering(Recommendation $recommendation): self
    {
        return new self(fn (): Recommendation => $recommendation);
    }

    /** Fail like a provider that answers with an error. */
    public static function failing(): self
    {
        return new self(fn (): Recommendation => throw new AiUnavailableException('recommendations.ai_failed'));
    }

    /** Run out of time at once, as the adapter does when the provider takes longer than the limit. */
    public static function timingOut(): self
    {
        return new self(fn (): Recommendation => throw new AiUnavailableException('recommendations.ai_timed_out'));
    }

    /**
     * Answer what the schema allows but the catalog does not: a moment, a climate and a budget that do not exist, a
     * perfume that is not in the catalog, the first perfume of the catalog twice and a suggestion without a reason.
     * Checking the answer against the catalog discards all of it (#119).
     */
    public static function answeringInvalidData(): self
    {
        return new self(function (string $text, array $catalog): Recommendation {
            $known = $catalog[0]->id ?? self::UNKNOWN_PERFUME;

            return new Recommendation(
                RecommendationOrigin::Ai,
                new CustomerPreferences(['vainilla'], 'madrugada', 'lluvioso', 'una cena', 0),
                [
                    new SuggestedPerfume(self::UNKNOWN_PERFUME, 'Un perfume que la tienda no vende.'),
                    new SuggestedPerfume($known, 'Su vainilla acompaña bien una cena.'),
                    new SuggestedPerfume($known, ''),
                ],
            );
        });
    }

    public function recommend(string $text, array $catalog): Recommendation
    {
        $this->received[] = ['text' => $text, 'catalog' => $catalog];

        return ($this->answer)($text, $catalog);
    }
}
