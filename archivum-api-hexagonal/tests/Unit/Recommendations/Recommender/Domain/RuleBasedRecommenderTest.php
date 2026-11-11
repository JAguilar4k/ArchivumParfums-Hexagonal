<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\Services\RuleBasedRecommender;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Recommendations\Recommender\Domain\ValueObjects\CustomerPreferences;
use Src\Recommendations\Recommender\Domain\ValueObjects\Recommendation;
use Src\Recommendations\Recommender\Domain\ValueObjects\RecommendationOrigin;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Tests\Support\Recommendations\FakeRuleReasons;

const RULES_AMBER_ID = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fa1';
const RULES_CITRUS_ID = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fa2';
const RULES_ROSE_ID = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fa3';
const RULES_LEATHER_ID = '019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fa4';

/**
 * The fictitious showcase of the store: an amber perfume for cold nights, a citrus one for warm days, a rose one for
 * temperate afternoons and a leather one without a presentation for sale.
 *
 * @return array<string, CatalogPerfume>
 */
function rulesShowcase(): array
{
    return [
        'amber' => new CatalogPerfume(RULES_AMBER_ID, 'Casa Ficticia', 'Ámbar de Medianoche', 'EDP', 'oriental', ['vainilla', 'ámbar', 'haba tonka'],
            ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => ['cena', 'cita']], [new CatalogVariant(50, 62_000), new CatalogVariant(100, 98_000)]),
        'citrus' => new CatalogPerfume(RULES_CITRUS_ID, 'Taller Inventado', 'Cítrico de Mediodía', 'EDT', 'cítrica', ['bergamota', 'limón'],
            ['time_of_day' => ['day'], 'climate' => ['warm'], 'occasion' => ['oficina']], [new CatalogVariant(100, 45_000)]),
        'rose' => new CatalogPerfume(RULES_ROSE_ID, 'Taller Inventado', 'Rosa de la Tarde', 'EDP', 'floral', ['rosa', 'pachulí'],
            ['time_of_day' => ['afternoon'], 'climate' => ['temperate'], 'occasion' => ['boda']], [new CatalogVariant(75, 70_000)]),
        'leather' => new CatalogPerfume(RULES_LEATHER_ID, 'Casa Ficticia', 'Cuero Sin Precio', 'EDP', 'cuero', ['cuero', 'vainilla'],
            ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => []], []),
    ];
}

/**
 * The recommendation of the rules for the text, with the given perfumes of the showcase, all of them by default.
 *
 * @param  list<string>  $perfumes  Keys of rulesShowcase(), in the order of the showcase.
 */
function rulesRecommend(string $text, array $perfumes = ['amber', 'citrus', 'rose', 'leather']): Recommendation
{
    $showcase = rulesShowcase();

    return (new RuleBasedRecommender(new FakeRuleReasons))->recommend($text, array_map(fn (string $key): CatalogPerfume => $showcase[$key], $perfumes));
}

it('reads the preferences in lowercase and without accents, among what the showcase has', function (): void {
    $recommendation = rulesRecommend('Busco algo con VAINILLA y Ambar para una cita de NOCHE, cuando hace frío. Máximo ₡100 000.');

    expect($recommendation->origin)->toBe(RecommendationOrigin::Rules)
        ->and($recommendation->preferences)->toEqual(new CustomerPreferences(['vainilla', 'ámbar'], 'night', 'cold', 'cita', 100_000));
});

it('leaves out of the preferences what the showcase does not have', function (): void {
    $recommendation = rulesRecommend('Algo con coco para la tarde en la playa, para una boda.', ['amber', 'citrus']);

    expect($recommendation->preferences)->toEqual(new CustomerPreferences([], null, null, null, null))
        ->and($recommendation->perfumes)->toBe([]);
});

it('takes the time of day, the climate and the occasion the text mentions first', function (): void {
    $recommendation = rulesRecommend('Para la noche o el día, con calor o con frío, para una boda o una cena.');

    expect($recommendation->preferences)->toEqual(new CustomerPreferences([], 'night', 'warm', 'boda', null));
});

it('finds a note in plural, a note of several words and the plain form of a note with accents', function (): void {
    $recommendation = rulesRecommend('Me gustan las rosas, el limon y la haba tonka.');

    expect($recommendation->preferences->notes)->toBe(['rosa', 'limón', 'haba tonka']);
});

it('reads the budget from the largest amount of money in the text', function (string $text, ?int $budget): void {
    expect(rulesRecommend($text)->preferences->budget)->toBe($budget);
})->with([
    'with the colón sign and a space' => ['Algo por ₡45 000.', 45_000],
    'with a dot for the thousands' => ['Hasta 45.000.', 45_000],
    'with a comma for the thousands' => ['Hasta 45,000.', 45_000],
    'five digits' => ['Un presupuesto de 45000.', 45_000],
    'in thousands with mil' => ['Unos 45 mil colones.', 45_000],
    'in thousands with k' => ['Máximo 45k.', 45_000],
    'with colones after it' => ['Tengo 9000 colones.', 9_000],
    'a range in thousands' => ['Entre 50 mil y 80 mil.', 80_000],
    'a range with mil only at the end' => ['Entre 50 y 80 mil.', 80_000],
    'ml are not money' => ['Un frasco de 100 ml o de 30ml.', null],
    'an age and a year are not money' => ['Para mis 30 años, en 2026.', null],
    'no amount' => ['Algo dulce.', null],
]);

it('R50: scores each perfume by what it shares with the text and suggests the best first', function (Recommendation $recommendation): void {
    expect($recommendation->perfumes)->toEqual([
        new SuggestedPerfume(RULES_AMBER_ID, 'notes: vainilla, ámbar; time of day: night'),
        new SuggestedPerfume(RULES_LEATHER_ID, 'notes: vainilla; time of day: night'),
    ]);
})->with([
    'the best one first in the showcase' => [fn () => rulesRecommend('Vainilla y ámbar para la noche.', ['amber', 'citrus', 'rose', 'leather'])],
    'the best one last in the showcase' => [fn () => rulesRecommend('Vainilla y ámbar para la noche.', ['leather', 'rose', 'citrus', 'amber'])],
]);

it('matches by tag: time of day, climate and occasion', function (string $text, string $perfume, string $reason): void {
    expect(rulesRecommend($text)->perfumes)->toEqual([new SuggestedPerfume($perfume, $reason)]);
})->with([
    'time of day' => ['Algo para la tarde.', RULES_ROSE_ID, 'time of day: afternoon'],
    'climate' => ['Algo para el calor.', RULES_CITRUS_ID, 'climate: warm'],
    'occasion' => ['Algo para la oficina.', RULES_CITRUS_ID, 'occasion: oficina'],
]);

it('keeps the order of the showcase among perfumes with the same score', function (): void {
    expect(rulesRecommend('Algo para la noche.')->perfumes)->toEqual([
        new SuggestedPerfume(RULES_AMBER_ID, 'time of day: night'),
        new SuggestedPerfume(RULES_LEATHER_ID, 'time of day: night'),
    ])->and(rulesRecommend('Algo para la noche.', ['leather', 'amber'])->perfumes)->toEqual([
        new SuggestedPerfume(RULES_LEATHER_ID, 'time of day: night'),
        new SuggestedPerfume(RULES_AMBER_ID, 'time of day: night'),
    ]);
});

it('never suggests a perfume over the budget, by the same criterion as the validation, and counts fitting it', function (): void {
    // The amber one starts at ₡62 000 and the rose one at ₡70 000; the leather one has no presentation for sale.
    $recommendation = rulesRecommend('Vainilla para la noche, máximo 50 mil.');

    expect($recommendation->preferences->budget)->toBe(50_000)
        ->and($recommendation->perfumes)->toEqual([new SuggestedPerfume(RULES_CITRUS_ID, 'budget')]);
});

it('suggests at most five perfumes', function (): void {
    $catalog = array_map(
        fn (int $index): CatalogPerfume => new CatalogPerfume("019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fb{$index}", 'Casa Ficticia', "Noche {$index}", null, null, [],
            ['time_of_day' => ['night'], 'climate' => [], 'occasion' => []], [new CatalogVariant(50, 40_000)]),
        range(1, 7),
    );

    $recommendation = (new RuleBasedRecommender(new FakeRuleReasons))->recommend('Algo para la noche.', $catalog);

    expect(array_map(fn (SuggestedPerfume $perfume): string => $perfume->perfumeId, $recommendation->perfumes))
        ->toBe(array_map(fn (CatalogPerfume $perfume): string => $perfume->id, array_slice($catalog, 0, Recommendation::MAX_PERFUMES)));
});

it('suggests nothing when the text shares nothing with the showcase', function (): void {
    $recommendation = rulesRecommend('Algo para regalar.');

    expect($recommendation)->toEqual(new Recommendation(RecommendationOrigin::Rules, new CustomerPreferences([], null, null, null, null), []));
});
