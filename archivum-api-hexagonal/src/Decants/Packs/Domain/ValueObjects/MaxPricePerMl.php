<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

/** The optional cap of an offer, which limits to which perfumes its decants apply (R32). */
final readonly class MaxPricePerMl
{
    /** Accept whole, positive colones per ml, up to what the column holds. */
    public function __construct(public int $amount)
    {
        if ($amount < 1 || $amount > Colones::MAX) {
            throw new InvalidValueException('packs.cap_invalid', ['max' => Colones::MAX]);
        }
    }

    /** Whether a presentation fits under the cap, compared without decimals: its sale price at most the cap times its ml. */
    public function allows(int $salePrice, int $presentationMl): bool
    {
        return $salePrice <= $this->amount * $presentationMl;
    }
}
