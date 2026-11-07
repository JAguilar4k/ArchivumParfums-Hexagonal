<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Application\Contracts\RecommendableCatalog;
use Src\Recommendations\Recommender\Application\DTOs\RecommendationResult;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedPerfume;
use Src\Recommendations\Recommender\Application\DTOs\RecommendedVariant;
use Src\Recommendations\Recommender\Application\UseCases\RecommendPerfumes;
use Src\Recommendations\Recommender\Domain\Services\RuleBasedRecommender;
use Src\Recommendations\Recommender\Domain\Services\SuggestionValidator;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Tests\Support\Recommendations\FakeAiRecommender;
use Tests\Support\Recommendations\FakeRuleReasons;

const AMBER_PERFUME = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f71';

const CITRUS_PERFUME = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f72';

/**
 * Two fictitious perfumes of a showcase: one for a cold night with vanilla and one for a warm day with lemon.
 *
 * @return list<CatalogPerfume>
 */
function useCaseCatalog(): array
{
    return [
        new CatalogPerfume(AMBER_PERFUME, 'Casa Ficticia', 'Ámbar de Medianoche', 'EDP', 'Oriental', ['Vainilla'],
            ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['Cena']], [new CatalogVariant(50, 98_000), new CatalogVariant(100, 150_000)]),
        new CatalogPerfume(CITRUS_PERFUME, 'Atelier Ficticio', 'Limón de Mediodía', 'EDT', 'Cítrica', ['Limón'],
            ['time_of_day' => ['day'], 'climate' => ['warm'], 'occasion' => ['Playa']], [new CatalogVariant(100, 75_000)]),
    ];
}

/**
 * The use case with a fixed showcase, the AI of the tests and the real validator and rules recommender.
 *
 * @param  list<CatalogPerfume>  $catalog
 */
function recommendPerfumes(FakeAiRecommender $ai, ?array $catalog = null): RecommendPerfumes
{
    $showcase = new class($catalog ?? useCaseCatalog()) implements RecommendableCatalog
    {
        /** @param  list<CatalogPerfume>  $perfumes */
        public function __construct(private readonly array $perfumes) {}

        public function perfumes(): array
        {
            return $this->perfumes;
        }
    };

    return new RecommendPerfumes($showcase, $ai, new SuggestionValidator, new RuleBasedRecommender(new FakeRuleReasons));
}

it('R48: answers what the AI suggests, with the brand, the name and the presentations of the catalog', function () {
    $ai = FakeAiRecommender::answering(new Recommendation(
        RecommendationOrigin::Ai,
        new CustomerPreferences(['Vainilla'], 'night', 'cold', 'Cena', 120_000),
        [new SuggestedPerfume(AMBER_PERFUME, '  Su vainilla abriga una noche fría.  ')],
    ));

    expect(recommendPerfumes($ai)->execute('Algo con vainilla para una cena en una noche fría, hasta 120 mil.'))->toEqual(new RecommendationResult(
        'ai', ['Vainilla'], 'night', 'cold', 'Cena', 120_000,
        [new RecommendedPerfume(AMBER_PERFUME, 'Casa Ficticia', 'Ámbar de Medianoche', 'Su vainilla abriga una noche fría.', [
            new RecommendedVariant(50, 98_000),
            new RecommendedVariant(100, 150_000),
        ])],
    ));
});

it('R49: keeps only the valid suggestions of the AI, in its order, and drops the preferences that do not exist', function () {
    $ai = FakeAiRecommender::answering(new Recommendation(
        RecommendationOrigin::Ai,
        new CustomerPreferences([], 'madrugada', 'cold', null, null),
        [
            new SuggestedPerfume(FakeAiRecommender::UNKNOWN_PERFUME, 'Un perfume de otra tienda.'),
            new SuggestedPerfume(CITRUS_PERFUME, 'Fresco para el día.'),
            new SuggestedPerfume(AMBER_PERFUME, 'Cálido para la noche.'),
            new SuggestedPerfume(CITRUS_PERFUME, 'Repetido.'),
        ],
    ));

    $result = recommendPerfumes($ai)->execute('Algo para cualquier momento.');

    expect($result->origin)->toBe('ai')
        ->and($result->timeOfDay)->toBeNull()
        ->and($result->climate)->toBe('cold')
        ->and(array_map(fn (RecommendedPerfume $perfume): string => $perfume->reason, $result->perfumes))->toBe(['Fresco para el día.', 'Cálido para la noche.']);
});

it('R50: answers with the rules when the AI fails, runs out of time or suggests nothing valid', function (FakeAiRecommender $ai) {
    $result = recommendPerfumes($ai)->execute('Busco algo con vainilla para la noche.');

    expect($result->origin)->toBe('rules')
        ->and($result->notes)->toBe(['Vainilla'])
        ->and($result->timeOfDay)->toBe('night')
        ->and($result->perfumes)->toEqual([new RecommendedPerfume(AMBER_PERFUME, 'Casa Ficticia', 'Ámbar de Medianoche', 'notes: Vainilla; time of day: night', [
            new RecommendedVariant(50, 98_000),
            new RecommendedVariant(100, 150_000),
        ])]);
})->with([
    'provider error' => [fn () => FakeAiRecommender::failing()],
    'time limit' => [fn () => FakeAiRecommender::timingOut()],
    'nothing valid' => [fn () => FakeAiRecommender::answeringInvalidData()],
]);

// R51 stays pending for #121, which also inspects the request to the provider with a signed-in customer.
it('sends the AI only the text of the customer and the catalog of the showcase', function () {
    $ai = FakeAiRecommender::failing();

    recommendPerfumes($ai)->execute('Algo con limón para la playa.');

    expect($ai->received)->toEqual([['text' => 'Algo con limón para la playa.', 'catalog' => useCaseCatalog()]]);
});

it('does not ask the AI when the showcase is empty, and recommends nothing', function () {
    $ai = FakeAiRecommender::answering(new Recommendation(
        RecommendationOrigin::Ai,
        new CustomerPreferences([], null, null, null, null),
        [new SuggestedPerfume(AMBER_PERFUME, 'Un perfume que esta tienda no tiene.')],
    ));

    $result = recommendPerfumes($ai, [])->execute('Algo con vainilla, hasta ₡50 000.');

    expect($ai->received)->toBe([])
        ->and($result)->toEqual(new RecommendationResult('rules', [], null, null, null, 50_000, []));
});
