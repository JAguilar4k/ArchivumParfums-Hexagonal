<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PerfumeVariantNotFoundException extends NotFoundException
{
    /** Build the exception for a presentation that the perfume does not contain. */
    public static function create(): self
    {
        return new self('catalog.variant_not_found');
    }
}
