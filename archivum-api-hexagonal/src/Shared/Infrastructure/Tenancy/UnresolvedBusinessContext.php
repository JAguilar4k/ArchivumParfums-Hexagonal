<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Tenancy;

use LogicException;
use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

final readonly class UnresolvedBusinessContext implements BusinessContext
{
    /** Fail loudly: only a route group with the business middleware knows the business. */
    public function current(): BusinessId
    {
        throw new LogicException('The business is unknown outside a route group with the business middleware.');
    }
}
