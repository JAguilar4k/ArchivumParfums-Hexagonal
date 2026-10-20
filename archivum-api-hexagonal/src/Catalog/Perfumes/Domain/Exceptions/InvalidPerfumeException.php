<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidPerfumeException extends InvalidValueException
{
    /** Build the exception for a note that the perfume already has. */
    public static function duplicateNote(): self
    {
        return new self('catalog.duplicate_note');
    }

    /** Build the exception for a tag that the perfume already has. */
    public static function duplicateTag(): self
    {
        return new self('catalog.duplicate_tag');
    }

    /** Build the exception for a presentation that the perfume does not contain. */
    public static function variantNotFound(): self
    {
        return new self('catalog.variant_not_found');
    }
}
