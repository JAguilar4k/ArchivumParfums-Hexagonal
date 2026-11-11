<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class DecantCount
{
    /** Most decants an offer sells together: the specification sets no limit (R32), so this one is a proposal. */
    public const int MAX = 50;

    /** Accept a whole, positive number of decants up to the limit. */
    public function __construct(public int $value)
    {
        if ($value < 1 || $value > self::MAX) {
            throw new InvalidValueException('packs.decants_invalid', ['max' => self::MAX]);
        }
    }
}
