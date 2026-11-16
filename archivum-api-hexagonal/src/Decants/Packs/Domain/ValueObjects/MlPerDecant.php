<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Decants\Packs\Domain\Exceptions\DecantSizeNotSoldException;
use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class MlPerDecant
{
    /** The same ceiling as the ml of a presentation (SizeMl); the store's decant sizes (R4) limit it further when an offer is defined. */
    public const int MAX = 32767;

    /** Accept only whole, positive milliliters that fit the column. */
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException('packs.ml_invalid', ['max' => self::MAX]);
        }
    }

    /**
     * The ml per decant of an offer being defined, as long as the store sells decants of that size (R4). A stored
     * offer is rebuilt with the constructor, so it keeps its ml if the store stops selling that size.
     *
     * @param  list<int>  $sizes  The decant sizes of the store, in ml.
     *
     * @throws DecantSizeNotSoldException
     */
    public static function soldBy(int $value, array $sizes): self
    {
        $ml = new self($value);

        if (! in_array($ml->value, $sizes, true)) {
            throw DecantSizeNotSoldException::create($sizes);
        }

        return $ml;
    }
}
