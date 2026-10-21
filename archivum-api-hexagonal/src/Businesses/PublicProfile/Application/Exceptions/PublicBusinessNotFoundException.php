<?php

declare(strict_types=1);

namespace Src\Businesses\PublicProfile\Application\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class PublicBusinessNotFoundException extends NotFoundException
{
    /** Build the exception for a business whose public address does not exist. */
    public static function create(): self
    {
        return new self('businesses.business_not_found');
    }
}
