<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\Contracts\PerfumeVariantPrices;
use Src\Catalog\Perfumes\Application\DTOs\AddPerfumeVariantCommand;
use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeVariantCommand;
use Src\Catalog\Perfumes\Application\UseCases\AddPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\RecalculateSalePrices;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Tests\Support\Catalog\FixedBottleMargin;
use Tests\Support\Catalog\FixedVariantCost;
use Tests\Support\Catalog\InMemoryPerfumeRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

/**
 * Wire the use cases that price a presentation with a cost of 80 000 colones and a margin of 5 000.
 *
 * @return array{AddPerfumeVariant, EditPerfumeVariant, Perfume}
 */
function pricingWiring(?int $cost = 80000): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $perfume = Perfume::create($perfumes->nextId(), new BusinessId('0192f0c4-eeee-7000-9000-000000000001'), new Brand('Casa Aurora'), new PerfumeName('Alba de Azahar'), PerfumeDetails::empty());
    $perfumes->save($perfume);
    $transaction = new RecordingTransactionRunner;
    $costs = new FixedVariantCost($cost);
    $margin = new FixedBottleMargin(5000);

    return [new AddPerfumeVariant($perfumes, $costs, $margin, $transaction), new EditPerfumeVariant($perfumes, $costs, $margin, $transaction), $perfume];
}

it('prices a new presentation with the cost plus the margin, or with its manual price', function () {
    [$add, , $perfume] = pricingWiring();

    $calculated = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, null));
    $manual = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 100, 99000));

    expect($calculated->salePrice?->amount)->toBe(85000)
        ->and($manual->salePrice?->amount)->toBe(99000);
});

it('leaves the sale price empty when the presentation has neither a cost nor a manual price', function () {
    [$add, , $perfume] = pricingWiring(cost: null);

    expect($add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, null))->salePrice)->toBeNull();
});

it('goes back to the calculation when the manual price is removed', function () {
    [$add, $edit, $perfume] = pricingWiring();
    $variant = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, 99000));

    $edited = $edit->execute(new EditPerfumeVariantCommand($perfume->id->value, $variant->id->value, ['manual_price' => null]));

    expect($edited->salePrice?->amount)->toBe(85000);
});

it('recalculates the prices without a manual price with the new margin', function () {
    $prices = new class implements PerfumeVariantPrices
    {
        /** @var list<?int> */
        public array $answers = [];

        public function recalculateWithoutManualPrice(BusinessId $business, callable $priceOf): int
        {
            $this->answers[] = $priceOf(new PerfumeVariantId('0192f0c4-0000-7000-9000-000000000001'))?->amount;

            return 1;
        }
    };

    $changed = (new RecalculateSalePrices($prices, new FixedVariantCost(80000), new RecordingTransactionRunner))
        ->execute('0192f0c4-eeee-7000-9000-000000000001', 12500);

    expect($changed)->toBe(1)
        ->and($prices->answers)->toBe([92500]);

    $withoutCost = (new RecalculateSalePrices($prices, new FixedVariantCost, new RecordingTransactionRunner))
        ->execute('0192f0c4-eeee-7000-9000-000000000001', 12500);

    expect($prices->answers)->toBe([92500, null])
        ->and($withoutCost)->toBe(1);
});
