<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Entities;

use Src\Catalog\Perfumes\Domain\Exceptions\DuplicateVariantSizeException;
use Src\Catalog\Perfumes\Domain\Exceptions\InvalidPerfumeException;
use Src\Catalog\Perfumes\Domain\Exceptions\PerfumeVariantNotFoundException;
use Src\Catalog\Perfumes\Domain\ValueObjects\Brand;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeDetails;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeId;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeName;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeNote;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeTag;
use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Shared\Domain\ValueObjects\BusinessId;
use Src\Shared\Domain\ValueObjects\Colones;

final class Perfume
{
    /**
     * Hold the full state of a perfume; notes, tags and presentations are kept in a fixed order.
     *
     * @param  list<PerfumeNote>  $notes
     * @param  list<PerfumeTag>  $tags
     * @param  list<PerfumeVariant>  $variants
     */
    private function __construct(
        public readonly PerfumeId $id,
        public readonly BusinessId $businessId,
        public private(set) Brand $brand,
        public private(set) PerfumeName $name,
        public private(set) PerfumeDetails $details,
        public private(set) array $notes,
        public private(set) array $tags,
        public private(set) bool $isActive,
        public private(set) array $variants,
    ) {
        $this->notes = self::sortedNotes($notes);
        $this->tags = self::sortedTags($tags);
        $this->variants = self::sortedVariants($variants);
    }

    /**
     * Register a new, active perfume without presentations.
     *
     * @param  list<PerfumeNote>  $notes
     * @param  list<PerfumeTag>  $tags
     */
    public static function create(
        PerfumeId $id,
        BusinessId $businessId,
        Brand $brand,
        PerfumeName $name,
        PerfumeDetails $details,
        array $notes = [],
        array $tags = [],
    ): self {
        self::assertNoDuplicates($notes, $tags);

        return new self($id, $businessId, $brand, $name, $details, $notes, $tags, true, []);
    }

    /**
     * Rebuild a perfume already stored, without applying creation defaults.
     *
     * @param  list<PerfumeNote>  $notes
     * @param  list<PerfumeTag>  $tags
     * @param  list<PerfumeVariant>  $variants
     */
    public static function reconstitute(
        PerfumeId $id,
        BusinessId $businessId,
        Brand $brand,
        PerfumeName $name,
        PerfumeDetails $details,
        array $notes,
        array $tags,
        bool $isActive,
        array $variants,
    ): self {
        return new self($id, $businessId, $brand, $name, $details, $notes, $tags, $isActive, $variants);
    }

    /**
     * Change the descriptive data, the notes and the tags; the business and the presentations stay.
     *
     * @param  list<PerfumeNote>  $notes
     * @param  list<PerfumeTag>  $tags
     */
    public function revise(Brand $brand, PerfumeName $name, PerfumeDetails $details, array $notes, array $tags): void
    {
        self::assertNoDuplicates($notes, $tags);

        $this->brand = $brand;
        $this->name = $name;
        $this->details = $details;
        $this->notes = self::sortedNotes($notes);
        $this->tags = self::sortedTags($tags);
    }

    /** Stop offering the perfume without deleting it or its presentations. */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /** Offer the perfume again. */
    public function activate(): void
    {
        $this->isActive = true;
    }

    /** Add an active presentation; the perfume refuses a second one with the same milliliters. */
    public function addVariant(PerfumeVariantId $id, SizeMl $size, ?Colones $salePrice, ?Colones $manualPrice): void
    {
        foreach ($this->variants as $variant) {
            if ($variant->size->equals($size)) {
                throw DuplicateVariantSizeException::create();
            }
        }

        $this->variants = self::sortedVariants([...$this->variants, PerfumeVariant::create($id, $size, $salePrice, $manualPrice)]);
    }

    /** Change the calculated price and the manual price of one presentation. */
    public function repriceVariant(PerfumeVariantId $id, ?Colones $salePrice, ?Colones $manualPrice): void
    {
        $this->variant($id)->reprice($salePrice, $manualPrice);
    }

    /** Stop offering one presentation without deleting it. */
    public function deactivateVariant(PerfumeVariantId $id): void
    {
        $this->variant($id)->deactivate();
    }

    /** Offer one presentation again. */
    public function activateVariant(PerfumeVariantId $id): void
    {
        $this->variant($id)->activate();
    }

    /**
     * Change the milliliters, the manual price and the state of one presentation; the perfume refuses milliliters that another has.
     *
     * @throws DuplicateVariantSizeException
     * @throws PerfumeVariantNotFoundException
     */
    public function editVariant(PerfumeVariantId $id, SizeMl $size, ?Colones $manualPrice, bool $isActive): void
    {
        $variant = $this->variant($id);

        foreach ($this->variants as $other) {
            if (! $other->id->equals($id) && $other->size->equals($size)) {
                throw DuplicateVariantSizeException::create();
            }
        }

        $variant->resize($size);
        $variant->reprice($variant->salePrice, $manualPrice);
        $isActive ? $variant->activate() : $variant->deactivate();

        $this->variants = self::sortedVariants($this->variants);
    }

    /**
     * Find one of the presentations of the perfume.
     *
     * @throws PerfumeVariantNotFoundException
     */
    public function variant(PerfumeVariantId $id): PerfumeVariant
    {
        foreach ($this->variants as $variant) {
            if ($variant->id->equals($id)) {
                return $variant;
            }
        }

        throw PerfumeVariantNotFoundException::create();
    }

    /**
     * @param  list<PerfumeNote>  $notes
     * @param  list<PerfumeTag>  $tags
     */
    private static function assertNoDuplicates(array $notes, array $tags): void
    {
        foreach ($notes as $index => $note) {
            foreach (array_slice($notes, $index + 1) as $other) {
                if ($note->equals($other)) {
                    throw InvalidPerfumeException::duplicateNote();
                }
            }
        }

        foreach ($tags as $index => $tag) {
            foreach (array_slice($tags, $index + 1) as $other) {
                if ($tag->equals($other)) {
                    throw InvalidPerfumeException::duplicateTag();
                }
            }
        }
    }

    /**
     * @param  list<PerfumeNote>  $notes
     * @return list<PerfumeNote>
     */
    private static function sortedNotes(array $notes): array
    {
        usort($notes, fn (PerfumeNote $a, PerfumeNote $b): int => [$a->layer->value, $a->note] <=> [$b->layer->value, $b->note]);

        return $notes;
    }

    /**
     * @param  list<PerfumeTag>  $tags
     * @return list<PerfumeTag>
     */
    private static function sortedTags(array $tags): array
    {
        usort($tags, fn (PerfumeTag $a, PerfumeTag $b): int => [$a->kind->value, $a->value] <=> [$b->kind->value, $b->value]);

        return $tags;
    }

    /**
     * @param  list<PerfumeVariant>  $variants
     * @return list<PerfumeVariant>
     */
    private static function sortedVariants(array $variants): array
    {
        usort($variants, fn (PerfumeVariant $a, PerfumeVariant $b): int => $a->size->value <=> $b->size->value);

        return $variants;
    }
}
