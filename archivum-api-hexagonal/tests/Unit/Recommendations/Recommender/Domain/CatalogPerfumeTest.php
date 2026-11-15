<?php

declare(strict_types=1);

use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogPerfume;
use Src\Recommendations\Recommender\Domain\ValueObjects\CatalogVariant;

/** @param  list<CatalogVariant>  $variants */
function budgetPerfume(array $variants): CatalogPerfume
{
    return new CatalogPerfume('019a1f2e-7c3b-7d4e-9a10-2b3c4d5e6f91', 'Casa Ficticia', 'Ámbar de Medianoche', 'EDP', 'oriental', ['vainilla'],
        ['time_of_day' => ['night'], 'climate' => ['cold'], 'occasion' => []], $variants);
}

it('fits a budget when its cheapest presentation costs at most the budget', function (int $budget, bool $fits): void {
    expect(budgetPerfume([new CatalogVariant(100, 98_000), new CatalogVariant(30, 45_000)])->fitsBudget($budget))->toBe($fits);
})->with([
    'under the budget' => [50_000, true],
    'at exactly the budget' => [45_000, true],
    'every presentation over the budget' => [44_999, false],
]);

it('fits no budget without a presentation for sale', function (): void {
    expect(budgetPerfume([])->fitsBudget(1_000_000))->toBeFalse();
});
