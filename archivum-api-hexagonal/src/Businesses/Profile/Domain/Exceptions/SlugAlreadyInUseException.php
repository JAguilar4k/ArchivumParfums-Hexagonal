<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class SlugAlreadyInUseException extends InvalidValueException
{
    /** Build the exception for a slug that another business already uses. */
    public static function create(): self
    {
        return new self('businesses.slug_already_in_use');
    }
}
