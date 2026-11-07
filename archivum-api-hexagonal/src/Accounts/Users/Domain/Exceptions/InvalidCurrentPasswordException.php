<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidCurrentPasswordException extends InvalidValueException
{
    /** Build the exception for a current password that does not match. */
    public static function create(): self
    {
        return new self('accounts.current_password_invalid');
    }
}
