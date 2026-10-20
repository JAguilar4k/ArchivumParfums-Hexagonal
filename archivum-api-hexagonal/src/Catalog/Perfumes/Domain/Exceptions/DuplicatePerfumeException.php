<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class DuplicatePerfumeException extends InvalidValueException
{
    /** Build the exception for a business that already has a perfume with that brand, name and concentration. */
    public static function create(): self
    {
        return new self('catalog.perfume_already_exists');
    }
}
