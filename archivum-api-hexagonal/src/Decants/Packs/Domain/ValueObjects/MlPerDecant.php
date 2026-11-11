<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class MlPerDecant
{
    /** Largest value the smallint column can store; the store's decant sizes (R4) limit it in the application. */
    public const int MAX = 32767;

    /** Accept only whole, positive milliliters that fit the column. */
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException('packs.ml_invalid', ['max' => self::MAX]);
        }
    }
}
