<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryStaffMemberships implements StaffMemberships
{
    /** @var array<string, StaffMembership> */
    private array $memberships = [];

    /** Put an account on a staff. */
    public function add(string $userId, StaffMembership $membership): void
    {
        $this->memberships[$userId] = $membership;
    }

    /** Find the membership of an account. */
    public function of(UserId $userId): ?StaffMembership
    {
        return $this->memberships[$userId->value] ?? null;
    }
}
