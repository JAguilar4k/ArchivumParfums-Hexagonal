<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class AccountNotFoundException extends NotFoundException
{
    /** Build the exception for an account that no longer exists. */
    public static function create(): self
    {
        return new self('accounts.account_not_found');
    }
}
