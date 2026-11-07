<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\AuthenticationFailedException;

final class SessionNotRenewableException extends AuthenticationFailedException
{
    /** Build the exception for a token that expired or was revoked before it could be renewed. */
    public static function create(): self
    {
        return new self('accounts.session_not_renewable');
    }
}
