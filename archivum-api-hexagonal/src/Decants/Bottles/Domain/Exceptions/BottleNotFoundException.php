<?php

declare(strict_types=1);

namespace Src\Decants\Bottles\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\NotFoundException;

final class BottleNotFoundException extends NotFoundException
{
    public static function create(): self
    {
        return new self('bottles.not_found');
    }
}
