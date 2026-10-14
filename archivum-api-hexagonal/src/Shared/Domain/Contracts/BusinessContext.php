<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\BusinessId;

interface BusinessContext
{
    /** Business that the current operation belongs to. */
    public function businessId(): BusinessId;
}
