<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class FindAccountRole
{
    /** Receive the staff memberships port. */
    public function __construct(private StaffMemberships $memberships) {}

    /** Answer the role of an account: the one of its staff membership or, without one, customer. */
    public function execute(string $userId): AccountRole
    {
        return $this->memberships->of(new UserId($userId))->role ?? AccountRole::Customer;
    }
}
