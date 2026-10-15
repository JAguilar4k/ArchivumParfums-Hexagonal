<?php

declare(strict_types=1);

namespace Src\Businesses\Profile\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class BusinessNotFoundException extends NotFoundException
{
    /** Build the exception for a business that does not exist. */
    public static function create(): self
    {
        return new self('businesses.business_not_found');
    }
}
