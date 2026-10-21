<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Entities;

use Src\Catalog\Perfumes\Domain\ValueObjects\PerfumeVariantId;
use Src\Catalog\Perfumes\Domain\ValueObjects\SizeMl;
use Src\Shared\Domain\ValueObjects\Colones;

final class PerfumeVariant
{
    /** Hold the full state of a presentation. */
    private function __construct(
        public readonly PerfumeVariantId $id,
        public readonly SizeMl $size,
        public private(set) ?Colones $salePrice,
        public private(set) ?Colones $manualPrice,
        public private(set) bool $isActive,
    ) {}

    /** Offer a new presentation, active from the start; its prices may be missing. */
    public static function create(PerfumeVariantId $id, SizeMl $size, ?Colones $salePrice, ?Colones $manualPrice): self
    {
        return new self($id, $size, $salePrice, $manualPrice, true);
    }

    /** Rebuild a presentation already stored, without applying creation defaults. */
    public static function reconstitute(
        PerfumeVariantId $id,
        SizeMl $size,
        ?Colones $salePrice,
        ?Colones $manualPrice,
        bool $isActive,
    ): self {
        return new self($id, $size, $salePrice, $manualPrice, $isActive);
    }

    /** Change the calculated price and the manual price of the owner. */
    public function reprice(?Colones $salePrice, ?Colones $manualPrice): void
    {
        $this->salePrice = $salePrice;
        $this->manualPrice = $manualPrice;
    }

    /** Stop offering the presentation without deleting it. */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /** Offer the presentation again. */
    public function activate(): void
    {
        $this->isActive = true;
    }
}
