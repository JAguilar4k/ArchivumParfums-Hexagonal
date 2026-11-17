<?php

declare(strict_types=1);

namespace Src\Scheduling\Services\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

/** A service that does not exist, or that belongs to another business, which answers 404 and never reveals it exists. */
final class ServiceNotFoundException extends NotFoundException
{
    /** Build the exception with the key the API translates. */
    public static function create(): self
    {
        return new self('scheduling.service.not_found');
    }
}
