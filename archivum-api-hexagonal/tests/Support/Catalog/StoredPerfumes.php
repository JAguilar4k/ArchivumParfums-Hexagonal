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
     */
    public static function create(
        string $businessId,
        string $brand = 'Tom Ford',
        string $name = 'Oud Wood',
        ?string $concentration = 'EDP',
        bool $active = true,
        array $variants = [],
        ?string $family = 'Amaderada',
    ): Perfume {
        app(BusinessTransaction::class)->scope($businessId);
        $repository = new EloquentPerfumeRepository(new FixedBusinessContext(new BusinessId($businessId)));

        $perfume = Perfume::create(
            $repository->nextId(),
            new BusinessId($businessId),
            new Brand($brand),
            new PerfumeName($name),
            new PerfumeDetails($concentration, 'unisex', $family, 'Madera de oud con especias.'),
            [new PerfumeNote(NoteLayer::Top, 'Cardamomo'), new PerfumeNote(NoteLayer::Base, 'Oud')],
            [PerfumeTag::timeOfDay(TimeOfDay::Night), PerfumeTag::climate(Climate::Cold), PerfumeTag::occasion('Cena')],
        );

        foreach ($variants as [$ml, $salePrice, $manualPrice]) {
            $perfume->addVariant(
                $repository->nextVariantId(),
                new SizeMl($ml),
                $salePrice === null ? null : new Colones($salePrice),
                $manualPrice === null ? null : new Colones($manualPrice),
            );
        }

        if (! $active) {
            $perfume->deactivate();
        }

        $repository->save($perfume);

        return $perfume;
    }
}
