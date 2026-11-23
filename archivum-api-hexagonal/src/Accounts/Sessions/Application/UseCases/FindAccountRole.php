<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Accounts\Users\Domain\Contracts\PlatformAdmins;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class FindAccountRole
{
    /** Receive the staff memberships and the platform administrators ports. */
    public function __construct(
        private StaffMemberships $memberships,
        private PlatformAdmins $platformAdmins,
    ) {}

    /** Answer the role of an account: platform administrator, the one of its staff membership or, without one, customer. */
    public function execute(string $userId): AccountRole
    {
        $id = new UserId($userId);

        return AccountRole::resolve($this->platformAdmins->includes($id), $this->memberships->of($id)?->role);
    }
}
