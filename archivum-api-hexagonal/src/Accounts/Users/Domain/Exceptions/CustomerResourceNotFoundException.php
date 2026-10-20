<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class CustomerResourceNotFoundException extends NotFoundException
{
    /** Build the exception for a resource that does not exist or belongs to another customer, which look the same. */
    public static function create(): self
    {
        return new self('accounts.resource_not_found');
    }
}
