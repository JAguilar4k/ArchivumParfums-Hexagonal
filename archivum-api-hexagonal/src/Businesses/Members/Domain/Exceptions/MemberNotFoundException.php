<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class MemberNotFoundException extends NotFoundException
{
    /** Build the exception for a person who is not on the staff of the business. */
    public static function create(): self
    {
        return new self('businesses.member_not_found');
    }
}
