<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

final class InsufficientMlException extends DomainException
{
    public static function create(): self
    {
        return new self('bottles.insufficient_ml');
    }
}
