<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Domain\ValueObjects\SalePrice;
use Src\Shared\Domain\ValueObjects\Colones;

it('adds the margin of the store to the cost when there is no manual price', function () {
    expect(SalePrice::calculate(new Colones(80000), new Colones(5000), null)?->amount)->toBe(85000);
});

it('uses the manual price as it is, whatever the cost and the margin are', function () {
    expect(SalePrice::calculate(new Colones(80000), new Colones(5000), new Colones(99000))?->amount)->toBe(99000)
        ->and(SalePrice::calculate(null, new Colones(5000), new Colones(99000))?->amount)->toBe(99000);
});

it('leaves the price empty when there is neither a cost nor a manual price', function () {
    expect(SalePrice::calculate(null, new Colones(5000), null))->toBeNull();
});

it('follows the margin of the store', function (int $margin, int $expected) {
    expect(SalePrice::calculate(new Colones(80000), new Colones($margin), null)?->amount)->toBe($expected);
})->with([
    'no margin' => [0, 80000],
    'the default margin' => [5000, 85000],
    'another margin' => [12500, 92500],
]);

it('keeps everything in whole colones even with large amounts', function () {
    $price = SalePrice::calculate(new Colones(1_000_000_000), new Colones(500_000_000), null);

    expect($price?->amount)->toBeInt()->toBe(1_500_000_000);
});
