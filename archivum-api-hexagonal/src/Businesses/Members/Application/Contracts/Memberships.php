<?php

declare(strict_types=1);

namespace Src\Businesses\Members\Application\Contracts;

use Src\Businesses\Members\Application\DTOs\Membership;
use Src\Shared\Domain\ValueObjects\UserId;

interface Memberships
{
    /** Find the business and role of an account before any business is pinned; an account is on one staff at most. */
    public function of(UserId $userId): ?Membership;
}
