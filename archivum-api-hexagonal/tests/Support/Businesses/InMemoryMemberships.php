<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use Src\Businesses\Members\Application\Contracts\Memberships;
use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryMemberships implements Memberships
{
    /** @var array<string, Membership> */
    private array $memberships = [];

    /** Put an account on the staff of a business. */
    public function add(string $userId, Membership $membership): void
    {
        $this->memberships[$userId] = $membership;
    }

    /** Find the business and role of an account. */
    public function of(UserId $userId): ?Membership
    {
        return $this->memberships[$userId->value] ?? null;
    }
}
