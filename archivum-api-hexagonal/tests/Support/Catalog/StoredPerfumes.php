<?php

declare(strict_types=1);

namespace Tests\Support\Catalog;

use Src\Catalog\Perfumes\Domain\Entities\Perfume;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\Climate;
use Src\Catalog\Perfumes\Domain\ValueObjects\NoteLayer;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Catalog\Perfumes\Domain\ValueObjects\TimeOfDay;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Models\PerfumeModel;
use Src\Catalog\Perfumes\Infrastructure\Persistence\Repositories\EloquentPerfumeRepository;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

/** Perfumes written straight through the repository, to give a test the catalog it needs without going through the API. */
final class StoredPerfumes
{
    /**
     * Save a fictitious perfume in a business, scoping the open transaction to it first, and return the aggregate.
     *
     * @param  list<array{int, int|null, int|null}>  $variants  Milliliters, sale price and manual price of each presentation.
     * @param  list<PerfumeNote>|null  $notes  The notes; by default one of the top and one of the base.
     * @param  list<PerfumeTag>|null  $tags  The tags; by default night, cold and the occasion Cena.
     * @param  list<int>  $inactiveSizes  Milliliters of the presentations to leave inactive.
     */
    public static function create(
        string $businessId,
        string $brand = 'Tom Ford',
        string $name = 'Oud Wood',
        ?string $concentration = 'EDP',
        bool $active = true,
        array $variants = [],
        ?string $family = 'Amaderada',
        ?array $notes = null,
        ?array $tags = null,
        array $inactiveSizes = [],
        ?string $photoPath = null,
    ): Perfume {
        app(BusinessTransaction::class)->scope($businessId);
        $repository = new EloquentPerfumeRepository(new FixedBusinessContext(new BusinessId($businessId)));

        $perfume = Perfume::create(
            $repository->nextId(),
            new BusinessId($businessId),
            new Brand($brand),
            new PerfumeName($name),
            new PerfumeDetails($concentration, 'unisex', $family, 'Madera de oud con especias.'),
            $notes ?? [new PerfumeNote(NoteLayer::Top, 'Cardamomo'), new PerfumeNote(NoteLayer::Base, 'Oud')],
            $tags ?? [PerfumeTag::timeOfDay(TimeOfDay::Night), PerfumeTag::climate(Climate::Cold), PerfumeTag::occasion('Cena')],
        );

        foreach ($variants as [$ml, $salePrice, $manualPrice]) {
            $perfume->addVariant(
                $repository->nextVariantId(),
                new SizeMl($ml),
                $salePrice === null ? null : new Colones($salePrice),
                $manualPrice === null ? null : new Colones($manualPrice),
            );
        }

        foreach ($perfume->variants as $variant) {
            if (in_array($variant->size->value, $inactiveSizes, true)) {
                $perfume->deactivateVariant($variant->id);
            }
        }

        if (! $active) {
            $perfume->deactivate();
        }

        $repository->save($perfume);

        if ($photoPath !== null) {
            PerfumeModel::query()->whereKey($perfume->id->value)->update(['image_path' => $photoPath]);
        }

        return $perfume;
    }
}
