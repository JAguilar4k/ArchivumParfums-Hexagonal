<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Users\Domain\Contracts\PlatformAdmins;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryPlatformAdmins implements PlatformAdmins
{
    /** @var array<string, true> */
    private array $admins = [];

    /** Mark an account as an administrator of the platform. */
    public function grant(UserId $userId): void
    {
        $this->admins[$userId->value] = true;
    }

    /** Tell whether an account was marked. */
    public function includes(UserId $userId): bool
    {
        return isset($this->admins[$userId->value]);
    }
}
