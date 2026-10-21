<?php

declare(strict_types=1);

namespace Src\Catalog\Showcase\Application\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class ShowcasePerfumeNotFoundException extends NotFoundException
{
    /** Build the exception for a perfume that the showcase does not show: it does not exist, is inactive, has nothing to sell, or is another store's. */
    public static function create(): self
    {
        return new self('catalog.perfume_not_found');
    }
}
