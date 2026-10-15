<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\Contracts;

use Src\Accounts\Sessions\Application\DTOs\StaffMembership;
use Src\Shared\Domain\ValueObjects\UserId;

interface StaffMemberships
{
    /** Find the role and the business of an account on a staff; null means the account is a customer. */
    public function of(UserId $userId): ?StaffMembership;
}
