<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\UseCases;

use Src\Businesses\Members\Application\Contracts\Memberships;
use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class FindMemberByAccount
{
    /** Receive the port that reads the memberships of the accounts. */
    public function __construct(private Memberships $memberships) {}

    /** Find the business and role of an account, or null when the account is on no staff. */
    public function execute(string $userId): ?Membership
    {
        return $this->memberships->of(new UserId($userId));
    }
}
