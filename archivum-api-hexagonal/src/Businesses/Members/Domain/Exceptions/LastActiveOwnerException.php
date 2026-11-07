<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class LastActiveOwnerException extends DomainException
{
    /** Build the exception for a change that would leave the business without an active owner. */
    public static function create(): self
    {
        return new self('businesses.last_active_owner');
    }
}
