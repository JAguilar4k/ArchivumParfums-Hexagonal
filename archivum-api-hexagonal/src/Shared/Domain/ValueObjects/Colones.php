<?php

declare(strict_types=1);

namespace Src\Shared\Domain\ValueObjects;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final readonly class Colones
{
    /** Accept only whole amounts that are not negative. */
    public function __construct(public int $amount)
    {
        if ($amount < 0) {
            throw new InvalidValueException('money.colones_negative');
        }
    }

    /** Compare two amounts by value. */
    public function equals(self $other): bool
    {
        return $other->amount === $this->amount;
    }
}
