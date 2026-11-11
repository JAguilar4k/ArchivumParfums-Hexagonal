<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\ValueObjects\RuleMatch;

it('scores two points for each shared note and one for each tag or the budget', function (RuleMatch $match, int $score): void {
    expect($match->score())->toBe($score);
})->with([
    'nothing shared' => [fn () => new RuleMatch('perfume', [], null, null, null, false), 0],
    'one note' => [fn () => new RuleMatch('perfume', ['vainilla'], null, null, null, false), 2],
    'two notes' => [fn () => new RuleMatch('perfume', ['vainilla', 'ámbar'], null, null, null, false), 4],
    'the time of day' => [fn () => new RuleMatch('perfume', [], 'night', null, null, false), 1],
    'the climate' => [fn () => new RuleMatch('perfume', [], null, 'cold', null, false), 1],
    'the occasion' => [fn () => new RuleMatch('perfume', [], null, null, 'cena', false), 1],
    'the budget' => [fn () => new RuleMatch('perfume', [], null, null, null, true), 1],
    'everything' => [fn () => new RuleMatch('perfume', ['vainilla', 'ámbar'], 'night', 'cold', 'cena', true), 8],
]);
