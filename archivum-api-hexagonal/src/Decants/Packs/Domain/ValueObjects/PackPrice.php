<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;
use Src\Shared\Domain\ValueObjects\Colones;

final readonly class PackPrice
{
    /** Accept the whole, positive colones the customer pays for the whole pack (R33), up to what the column holds. */
    public function __construct(public int $amount)
    {
        if ($amount < 1 || $amount > Colones::MAX) {
            throw new InvalidValueException('packs.price_invalid', ['max' => Colones::MAX]);
        }
    }
}
