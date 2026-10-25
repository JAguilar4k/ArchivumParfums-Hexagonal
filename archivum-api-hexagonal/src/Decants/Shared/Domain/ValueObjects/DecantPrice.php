<?php

declare(strict_types=1);

namespace Src\Decants\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DecantPrice
{
    /** Round the proportional price upward using integers only. */
    public static function calculate(int $salePrice, int $variantMl, int $decantMl, int $rounding = 100): int
    {
        if ($salePrice < 0 || $variantMl <= 0 || $decantMl <= 0 || $rounding <= 0) {
            throw new InvalidValueException('bottles.invalid_price');
        }

        $denominator = $variantMl * $rounding;

        return intdiv($salePrice * $decantMl + $denominator - 1, $denominator) * $rounding;
    }
}
