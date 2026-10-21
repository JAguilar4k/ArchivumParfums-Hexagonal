<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class DuplicateVariantSizeException extends InvalidValueException
{
    /** Build the exception for a perfume that already has a presentation of those milliliters. */
    public static function create(): self
    {
        return new self('catalog.duplicate_variant_size');
    }
}
