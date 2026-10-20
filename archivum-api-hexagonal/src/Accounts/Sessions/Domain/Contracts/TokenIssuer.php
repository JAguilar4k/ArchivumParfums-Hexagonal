<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\UserId;

interface TokenIssuer
{
    /** Issue a new access token for a device of the account and return it in plain text, the only time it is shown. */
    public function issue(UserId $userId, string $deviceName): string;
}
