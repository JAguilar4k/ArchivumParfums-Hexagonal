<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Queue;

use Src\Shared\Domain\Contracts\BusinessContext;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** The business a job of the queue carries; ScopeJobToBusiness binds it while the job runs (#156). */
final readonly class JobBusinessContext implements BusinessContext
{
    public function __construct(private BusinessId $business) {}

    public function current(): BusinessId
    {
        return $this->business;
    }
}
