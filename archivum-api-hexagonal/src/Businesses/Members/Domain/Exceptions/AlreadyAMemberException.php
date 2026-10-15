<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\InvalidValueException;

final class AlreadyAMemberException extends InvalidValueException
{
    /** Build the exception for an account that already belongs to the staff of a business. */
    public static function create(): self
    {
        return new self('businesses.already_a_member');
    }
}
