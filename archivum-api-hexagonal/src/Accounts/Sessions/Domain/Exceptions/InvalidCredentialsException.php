<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidCredentialsException extends InvalidValueException
{
    /** Build the one exception for an unknown email or a wrong password, so neither is told apart. */
    public static function create(): self
    {
        return new self('accounts.invalid_credentials');
    }
}
