<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\Exceptions\AiUnavailableException;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Tests\Support\Recommendations\FakeAiRecommender;

/**
 * One fictitious perfume of a showcase.
 *
 * @return list<CatalogPerfume>
 */
function fakeRecommenderCatalog(): array
{
    return [new CatalogPerfume('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f71', 'Casa Ficticia', 'Ámbar de Medianoche', 'EDP', 'oriental',
        ['vainilla'], ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => []], [new CatalogVariant(100, 98_000)])];
}

it('answers the fixed recommendation and remembers the text and the catalog it received', function (): void {
    $recommendation = new Recommendation(
        RecommendationOrigin::Ai,
        new CustomerPreferences(['vainilla'], 'night', null, null, null),
        [new SuggestedPerfume('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f71', 'Su vainilla abriga una noche fría.')],
    );
    $recommender = FakeAiRecommender::answering($recommendation);

    expect($recommender->recommend('Algo con vainilla para la noche.', fakeRecommenderCatalog()))->toBe($recommendation)
        ->and($recommender->received)->toEqual([['text' => 'Algo con vainilla para la noche.', 'catalog' => fakeRecommenderCatalog()]]);
});

it('fails, or runs out of time, at once and without a provider', function (FakeAiRecommender $recommender, string $cause): void {
    $startedAt = hrtime(true);

    expect(fn () => $recommender->recommend('Algo con vainilla.', fakeRecommenderCatalog()))
        ->toThrow(AiUnavailableException::class, $cause)
        ->and((hrtime(true) - $startedAt) / 1e9)->toBeLessThan(1.0);
})->with([
    'provider error' => [fn () => FakeAiRecommender::failing(), 'recommendations.ai_failed'],
    'time limit' => [fn () => FakeAiRecommender::timingOut(), 'recommendations.ai_timed_out'],
]);

it('answers data that the schema allows and the catalog does not', function (): void {
    $catalog = fakeRecommenderCatalog();

    $recommendation = FakeAiRecommender::answeringInvalidData()->recommend('Algo para una cena.', $catalog);

    expect($recommendation->preferences)->toEqual(new CustomerPreferences(['vainilla'], 'madrugada', 'lluvioso', 'una cena', 0))
        ->and($recommendation->perfumes)->toEqual([
            new SuggestedPerfume(FakeAiRecommender::UNKNOWN_PERFUME, 'Un perfume que la tienda no vende.'),
            new SuggestedPerfume($catalog[0]->id, 'Su vainilla acompaña bien una cena.'),
            new SuggestedPerfume($catalog[0]->id, ''),
        ]);
});
