<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;

interface AuditTrail
{
    /**
     * Leave an entry in the transaction of the change: if it fails, the change is not saved. Who did it and the request
     * come from the running request; without an account, the author is the system.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        BusinessId $businessId,
        AuditAction $action,
        string $resourceType,
        string $resourceId,
        ?array $before,
        ?array $after,
    ): void;
}
