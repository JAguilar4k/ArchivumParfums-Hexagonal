<?php

declare(strict_types=1);

use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\InvalidPerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeVariantNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\Climate;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Catalog\Perfumes\Domain\ValueObjects\TimeOfDay;
use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

const PERFUME_UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6b';
const OTHER_PERFUME_UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6c';
const BUSINESS_OF_PERFUME_UUID = '0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6d';

/** A fictitious perfume with a note and a tag, and without presentations. */
function newPerfume(): Perfume
{
    return Perfume::create(
        new PerfumeId(PERFUME_UUID),
        new BusinessId(BUSINESS_OF_PERFUME_UUID),
        new Brand('Tom Ford'),
        new PerfumeName('Oud Wood'),
        PerfumeDetails::empty(),
        [new PerfumeNote(NoteLayer::Base, 'Oud')],
        [PerfumeTag::timeOfDay(TimeOfDay::Night)],
    );
}

it('creates an active perfume without presentations', function (): void {
    $perfume = newPerfume();

    expect($perfume->isActive)->toBeTrue()
        ->and($perfume->variants)->toBe([])
        ->and($perfume->brand->value)->toBe('Tom Ford')
        ->and($perfume->businessId->value)->toBe(BUSINESS_OF_PERFUME_UUID);
});

it('adds presentations in order of size and refuses a second one with the same milliliters', function (): void {
    $perfume = newPerfume();
    $perfume->addVariant(new PerfumeVariantId(OTHER_PERFUME_UUID), new SizeMl(10), new Colones(8000), null);
    $perfume->addVariant(new PerfumeVariantId(PERFUME_UUID), new SizeMl(5), null, new Colones(5000));

    expect(array_map(fn ($variant) => $variant->size->value, $perfume->variants))->toBe([5, 10]);

    $perfume->addVariant(new PerfumeVariantId('0190a1b2-c3d4-7e5f-8a6b-1c2d3e4f5a6e'), new SizeMl(10), null, null);
})->throws(DuplicateVariantSizeException::class, 'catalog.duplicate_variant_size');

it('deactivates and reactivates a perfume and a presentation without deleting anything', function (): void {
    $perfume = newPerfume();
    $variantId = new PerfumeVariantId(OTHER_PERFUME_UUID);
    $perfume->addVariant($variantId, new SizeMl(5), null, null);

    $perfume->deactivate();
    $perfume->deactivateVariant($variantId);

    expect($perfume->isActive)->toBeFalse()
        ->and($perfume->variants)->toHaveCount(1)
        ->and($perfume->variants[0]->isActive)->toBeFalse();

    $perfume->activate();
    $perfume->activateVariant($variantId);

    expect($perfume->isActive)->toBeTrue()
        ->and($perfume->variants[0]->isActive)->toBeTrue();
});

it('refuses to change a presentation that the perfume does not contain', function (): void {
    newPerfume()->deactivateVariant(new PerfumeVariantId(OTHER_PERFUME_UUID));
})->throws(PerfumeVariantNotFoundException::class, 'catalog.variant_not_found');

it('edits the milliliters, the manual price and the state of a presentation', function (): void {
    $perfume = newPerfume();
    $id = new PerfumeVariantId(OTHER_PERFUME_UUID);
    $perfume->addVariant($id, new SizeMl(5), new Colones(9000), null);
    $perfume->addVariant(new PerfumeVariantId(PERFUME_UUID), new SizeMl(2), null, null);

    $perfume->editVariant($id, new SizeMl(1), new Colones(12000), false);

    $variant = $perfume->variant($id);
    expect($variant->size->value)->toBe(1)
        ->and($variant->manualPrice?->amount)->toBe(12000)
        ->and($variant->salePrice?->amount)->toBe(9000)
        ->and($variant->isActive)->toBeFalse()
        ->and(array_map(fn ($variant) => $variant->size->value, $perfume->variants))->toBe([1, 2]);
});

it('refuses to give a presentation the milliliters of another one but keeps its own', function (): void {
    $perfume = newPerfume();
    $id = new PerfumeVariantId(OTHER_PERFUME_UUID);
    $perfume->addVariant($id, new SizeMl(5), null, null);
    $perfume->addVariant(new PerfumeVariantId(PERFUME_UUID), new SizeMl(10), null, null);

    $perfume->editVariant($id, new SizeMl(5), null, true);

    expect(fn () => $perfume->editVariant($id, new SizeMl(10), null, true))
        ->toThrow(DuplicateVariantSizeException::class);
});

it('refuses a repeated note, ignoring case', function (): void {
    Perfume::create(
        new PerfumeId(PERFUME_UUID),
        new BusinessId(BUSINESS_OF_PERFUME_UUID),
        new Brand('Tom Ford'),
        new PerfumeName('Oud Wood'),
        PerfumeDetails::empty(),
        [new PerfumeNote(NoteLayer::Top, 'Rosa'), new PerfumeNote(NoteLayer::Top, 'rosa')],
    );
})->throws(InvalidPerfumeException::class, 'catalog.duplicate_note');

it('refuses a repeated tag', function (): void {
    Perfume::create(
        new PerfumeId(PERFUME_UUID),
        new BusinessId(BUSINESS_OF_PERFUME_UUID),
        new Brand('Tom Ford'),
        new PerfumeName('Oud Wood'),
        PerfumeDetails::empty(),
        [],
        [PerfumeTag::climate(Climate::Warm), PerfumeTag::climate(Climate::Warm)],
    );
})->throws(InvalidPerfumeException::class, 'catalog.duplicate_tag');

it('rejects the data that the value objects cannot hold', function (callable $build, string $key): void {
    expect($build)->toThrow(InvalidValueException::class, $key);
})->with([
    'empty brand' => [fn () => new Brand('  '), 'catalog.brand_invalid'],
    'empty name' => [fn () => new PerfumeName(''), 'catalog.name_invalid'],
    'zero milliliters' => [fn () => new SizeMl(0), 'catalog.size_ml_invalid'],
    'negative price' => [fn () => new Colones(-1), 'money.colones_negative'],
    'moment outside the list' => [fn () => PerfumeTag::fromStored('time_of_day', 'evening'), 'catalog.time_of_day_invalid'],
    'climate outside the list' => [fn () => PerfumeTag::fromStored('climate', 'hot'), 'catalog.climate_invalid'],
    'layer outside the list' => [fn () => PerfumeNote::fromStored('middle', 'Rosa'), 'catalog.note_layer_invalid'],
]);
