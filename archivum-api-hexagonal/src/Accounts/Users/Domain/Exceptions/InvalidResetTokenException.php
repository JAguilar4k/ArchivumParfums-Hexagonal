<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class InvalidResetTokenException extends InvalidValueException
{
    /** Build the exception for a recovery token that expired, was used or belongs to another email. */
    public static function create(): self
    {
        return new self('accounts.reset_token_invalid');
    }
}
