<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\Contracts\RuleReasons;
use Src\Recommendations\Recommender\Domain\Services\RuleBasedRecommender;
use Src\Recommendations\Recommender\Domain\Services\SuggestionValidator;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;
use Src\Recommendations\Recommender\Domain\ValueObjects\Climate;
use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;
use Src\Recommendations\Recommender\Domain\ValueObjects\SuggestedPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\TimeOfDay;

it('writes one sentence in Spanish for each thing the perfume shares with the text', function (): void {
    $reason = app(RuleReasons::class)->write(new RuleMatch('perfume', ['vainilla', 'ámbar'], 'night', 'cold', 'cita', true));

    expect($reason)->toBe('Tiene las notas pedidas: vainilla y ámbar. Va bien de noche. Rinde bien en clima frío. Encaja con la ocasión: cita. Cabe en el presupuesto.')
        ->and(mb_strlen($reason))->toBeLessThanOrEqual(SuggestionValidator::MAX_REASON_LENGTH);
});

it('writes the shared notes as a list in Spanish', function (RuleMatch $match, string $reason): void {
    expect(app(RuleReasons::class)->write($match))->toBe($reason);
})->with([
    'one note' => [fn () => new RuleMatch('perfume', ['vainilla'], null, null, null, false), 'Tiene las notas pedidas: vainilla.'],
    'two notes' => [fn () => new RuleMatch('perfume', ['vainilla', 'ámbar'], null, null, null, false), 'Tiene las notas pedidas: vainilla y ámbar.'],
    'three notes' => [fn () => new RuleMatch('perfume', ['vainilla', 'ámbar', 'haba tonka'], null, null, null, false), 'Tiene las notas pedidas: vainilla, ámbar y haba tonka.'],
]);

it('has a sentence for every time of day and every climate', function (): void {
    foreach (TimeOfDay::cases() as $timeOfDay) {
        $reason = app(RuleReasons::class)->write(new RuleMatch('perfume', [], $timeOfDay->value, null, null, false));

        expect($reason)->toStartWith('Va bien')->and($reason)->not->toContain('recommendations.');
    }

    foreach (Climate::cases() as $climate) {
        $reason = app(RuleReasons::class)->write(new RuleMatch('perfume', [], null, $climate->value, null, false));

        expect($reason)->toStartWith('Rinde bien en clima')->and($reason)->not->toContain('recommendations.');
    }
});

it('builds the rules recommender with the reasons of lang/es', function (): void {
    $catalog = [new CatalogPerfume('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fc1', 'Casa Ficticia', 'Ámbar de Medianoche', 'EDP', 'oriental', ['vainilla'],
        ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => []], [new CatalogVariant(100, 98_000)])];

    $recommendation = app(RuleBasedRecommender::class)->recommend('Algo con vainilla para la noche.', $catalog);

    expect($recommendation->perfumes)->toEqual([
        new SuggestedPerfume('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6fc1', 'Tiene las notas pedidas: vainilla. Va bien de noche.'),
    ]);
});
