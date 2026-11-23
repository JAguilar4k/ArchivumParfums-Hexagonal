<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\UserId;

/**
 * Accounts that administer the platform (#347): they create and list businesses, and belong to none of them.
 */
interface PlatformAdmins
{
    /** Mark an account as an administrator of the platform. */
    public function grant(UserId $userId): void;

    /** Tell whether an account administers the platform. */
    public function includes(UserId $userId): bool;
}
