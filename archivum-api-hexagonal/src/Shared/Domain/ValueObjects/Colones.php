<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Colones
{
    /** Largest amount a column INTEGER of PostgreSQL holds; a technical limit, not a commercial one. */
    public const int MAX = 2147483647;

    /** Accept only whole amounts that are not negative and fit in the INTEGER columns. */
    public function __construct(public int $amount)
    {
        if ($amount < 0) {
            throw new InvalidValueException('money.colones_negative');
        }

        if ($amount > self::MAX) {
            throw new InvalidValueException('money.colones_too_large', ['max' => self::MAX]);
        }
    }

    /** Compare two amounts by value. */
    public function equals(self $other): bool
    {
        return $other->amount === $this->amount;
    }
}
