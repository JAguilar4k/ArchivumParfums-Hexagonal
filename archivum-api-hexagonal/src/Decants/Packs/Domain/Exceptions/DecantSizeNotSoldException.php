<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class DecantSizeNotSoldException extends InvalidValueException
{
    /**
     * Build the exception for ml per decant that are not one of the decant sizes of the store (R4).
     *
     * @param  list<int>  $sizes  The decant sizes of the store, in ml.
     */
    public static function create(array $sizes): self
    {
        sort($sizes);

        return new self('packs.size_not_sold', ['sizes' => implode(', ', $sizes)]);
    }
}
