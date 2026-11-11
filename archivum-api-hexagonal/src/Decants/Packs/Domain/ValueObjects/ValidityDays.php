<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class ValidityDays
{
    /** Longest validity of a pack: the specification sets no limit (R32), so this one is a proposal. */
    public const int MAX = 365;

    /** Accept a whole, positive number of days up to the limit. */
    public function __construct(public int $days)
    {
        if ($days < 1 || $days > self::MAX) {
            throw new InvalidValueException('packs.validity_invalid', ['max' => self::MAX]);
        }
    }
}
