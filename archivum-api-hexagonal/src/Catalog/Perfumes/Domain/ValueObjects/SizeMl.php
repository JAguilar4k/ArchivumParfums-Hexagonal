<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class SizeMl
{
    /** Largest value the smallint column can store; the business ceiling is still undecided. */
    public const int MAX = 32767;

    /** Accept only whole, positive milliliters that fit the column. */
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException('catalog.size_ml_invalid', ['max' => self::MAX]);
        }
    }

    /** Compare two sizes by value. */
    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}
