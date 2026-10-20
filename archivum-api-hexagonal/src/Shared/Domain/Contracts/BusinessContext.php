<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\BusinessId;

interface BusinessContext
{
    /** Identify the business the current operation belongs to. */
    public function current(): BusinessId;
}
