<?php

declare(strict_types=1);

namespace Tests\Support\Shared;

use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;

final class RecordingAuditTrail implements AuditTrail
{
    /** @var list<array{action: AuditAction, resource: string, before: array<string, mixed>|null, after: array<string, mixed>|null}> */
    public private(set) array $entries = [];

    /**
     * Remember the entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(BusinessId $businessId, AuditAction $action, string $resourceType, string $resourceId, ?array $before, ?array $after): void
    {
        $this->entries[] = ['action' => $action, 'resource' => "{$resourceType}:{$resourceId}", 'before' => $before, 'after' => $after];
    }
}
