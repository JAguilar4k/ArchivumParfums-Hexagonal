<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Exceptions;

use Src\Shared\Domain\Exceptions\DomainException;

/** A key names one purchase: sending it again for another offer is a conflict. */
final class IdempotencyKeyReusedException extends DomainException
{
    public static function create(): self
    {
        return new self('packs.idempotency_key_reused');
    }
}
