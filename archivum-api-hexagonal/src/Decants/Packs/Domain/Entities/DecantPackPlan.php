<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Entities;

use Src\Decants\Packs\Domain\ValueObjects\DecantCount;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackPlanId;
use Src\Decants\Packs\Domain\ValueObjects\MaxPricePerMl;
use Src\Decants\Packs\Domain\ValueObjects\MlPerDecant;
use Src\Decants\Packs\Domain\ValueObjects\PackPrice;
use Src\Decants\Packs\Domain\ValueObjects\PlanName;
use Src\Decants\Packs\Domain\ValueObjects\ValidityDays;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** An offer of the store (R32): how many decants of how many ml, its price, its validity and an optional cap per ml. */
final class DecantPackPlan
{
    /** Hold the full state of an offer. */
    private function __construct(
        public readonly DecantPackPlanId $id,
        public readonly BusinessId $businessId,
        public readonly PlanName $name,
        public readonly DecantCount $decants,
        public readonly MlPerDecant $mlPerDecant,
        public readonly PackPrice $price,
        public readonly ValidityDays $validity,
        public readonly ?MaxPricePerMl $maxPricePerMl,
        public private(set) bool $isActive,
    ) {}

    /** Define an offer that is on sale from the start; the value objects already refused what is not a positive integer. */
    public static function create(DecantPackPlanId $id, BusinessId $businessId, PlanName $name, DecantCount $decants, MlPerDecant $mlPerDecant, PackPrice $price, ValidityDays $validity, ?MaxPricePerMl $maxPricePerMl): self
    {
        return new self($id, $businessId, $name, $decants, $mlPerDecant, $price, $validity, $maxPricePerMl, true);
    }

    /** Rebuild an offer from its stored state. */
    public static function reconstitute(DecantPackPlanId $id, BusinessId $businessId, PlanName $name, DecantCount $decants, MlPerDecant $mlPerDecant, PackPrice $price, ValidityDays $validity, ?MaxPricePerMl $maxPricePerMl, bool $isActive): self
    {
        return new self($id, $businessId, $name, $decants, $mlPerDecant, $price, $validity, $maxPricePerMl, $isActive);
    }

    /** Take the offer off sale. It is not deleted: the packs bought from it keep pointing to it. */
    public function deactivate(): void
    {
        $this->isActive = false;
    }

    /** Put an inactive offer on sale again. */
    public function reactivate(): void
    {
        $this->isActive = true;
    }
}
