<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Application\DTOs\AddPerfumeVariantCommand;
use Src\Catalog\Perfumes\Application\DTOs\EditPerfumeVariantCommand;
use Src\Catalog\Perfumes\Application\UseCases\AddPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\EditPerfumeVariant;
use Src\Catalog\Perfumes\Application\UseCases\ListPerfumeVariants;
use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeNotFoundException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeVariantNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Tests\Support\Catalog\FixedBottleMargin;
use Tests\Support\Catalog\FixedVariantCost;
use Tests\Support\Catalog\InMemoryPerfumeRepository;
use Tests\Support\Shared\RecordingTransactionRunner;

/**
 * Wire the use cases to an in-memory repository that already holds one perfume without presentations.
 *
 * @return array{AddPerfumeVariant, EditPerfumeVariant, ListPerfumeVariants, Perfume}
 */
function variantsWiring(): array
{
    $perfumes = new InMemoryPerfumeRepository;
    $perfume = Perfume::create($perfumes->nextId(), new BusinessId('0192f0c4-dddd-7000-9000-000000000001'), new Brand('Casa Aurora'), new PerfumeName('Alba de Azahar'), PerfumeDetails::empty());
    $perfumes->save($perfume);
    $transaction = new RecordingTransactionRunner;

    return [new AddPerfumeVariant($perfumes, new FixedVariantCost, new FixedBottleMargin, $transaction), new EditPerfumeVariant($perfumes, new FixedVariantCost, new FixedBottleMargin, $transaction), new ListPerfumeVariants($perfumes), $perfume];
}

it('adds an active presentation with or without a manual price', function () {
    [$add, , $list, $perfume] = variantsWiring();

    $withPrice = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, 98000));
    $withoutPrice = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 100, null));

    expect($withPrice->manualPrice?->amount)->toBe(98000)
        ->and($withPrice->isActive)->toBeTrue()
        ->and($withoutPrice->manualPrice)->toBeNull()
        ->and(array_map(fn ($variant) => $variant->size->value, $list->execute($perfume->id->value)))->toBe([50, 100]);
});

it('refuses a second presentation with the same milliliters', function () {
    [$add, , , $perfume] = variantsWiring();
    $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, null));

    expect(fn () => $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, 70000)))
        ->toThrow(DuplicateVariantSizeException::class);
});

it('rejects milliliters and prices the domain cannot hold', function (int $ml, ?int $price) {
    [$add, , , $perfume] = variantsWiring();

    expect(fn () => $add->execute(new AddPerfumeVariantCommand($perfume->id->value, $ml, $price)))
        ->toThrow(InvalidValueException::class);
})->with([
    'zero milliliters' => [0, null],
    'negative price' => [50, -1],
]);

it('edits the milliliters, removes the manual price and deactivates a presentation', function () {
    [$add, $edit, , $perfume] = variantsWiring();
    $variant = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, 98000));

    $edited = $edit->execute(new EditPerfumeVariantCommand($perfume->id->value, $variant->id->value, ['ml' => 60, 'manual_price' => null, 'active' => false]));

    expect($edited->size->value)->toBe(60)
        ->and($edited->manualPrice)->toBeNull()
        ->and($edited->isActive)->toBeFalse();

    $reactivated = $edit->execute(new EditPerfumeVariantCommand($perfume->id->value, $variant->id->value, ['active' => true]));
    expect($reactivated->isActive)->toBeTrue()
        ->and($reactivated->size->value)->toBe(60);
});

it('treats a perfume or a presentation that is not there as not found', function () {
    [$add, $edit, $list, $perfume] = variantsWiring();
    $variant = $add->execute(new AddPerfumeVariantCommand($perfume->id->value, 50, null));
    $unknown = '0192f0c4-0000-7000-9000-999999999999';

    expect(fn () => $list->execute($unknown))->toThrow(PerfumeNotFoundException::class)
        ->and(fn () => $add->execute(new AddPerfumeVariantCommand('no-es-un-uuid', 50, null)))->toThrow(PerfumeNotFoundException::class)
        ->and(fn () => $edit->execute(new EditPerfumeVariantCommand($unknown, $variant->id->value, ['ml' => 60])))->toThrow(PerfumeNotFoundException::class)
        ->and(fn () => $edit->execute(new EditPerfumeVariantCommand($perfume->id->value, $unknown, ['ml' => 60])))->toThrow(PerfumeVariantNotFoundException::class)
        ->and(fn () => $edit->execute(new EditPerfumeVariantCommand($perfume->id->value, 'no-es-un-uuid', ['ml' => 60])))->toThrow(PerfumeVariantNotFoundException::class);
});
