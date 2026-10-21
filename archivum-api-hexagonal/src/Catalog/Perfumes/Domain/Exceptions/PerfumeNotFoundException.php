<?php

declare(strict_types=1);

namespace Src\Catalog\Perfumes\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PerfumeNotFoundException extends NotFoundException
{
    /** Build the exception for a perfume that does not exist in the business, or belongs to another one. */
    public static function create(): self
    {
        return new self('catalog.perfume_not_found');
    }
}
