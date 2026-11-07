<?php

declare(strict_types=1);

namespace Database\Seeders\Concerns;

use Src\Shared\Domain\Contracts\AuditTrail;
use Src\Shared\Domain\ValueObjects\AuditAction;
use Src\Shared\Domain\ValueObjects\BusinessId;

/** The demo data is not a change anybody made: seeding it leaves no entries in the log. */
final readonly class SeededAuditTrail implements AuditTrail
{
    /**
     * Leave no entry.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(BusinessId $businessId, AuditAction $action, string $resourceType, string $resourceId, ?array $before, ?array $after): void {}
}
