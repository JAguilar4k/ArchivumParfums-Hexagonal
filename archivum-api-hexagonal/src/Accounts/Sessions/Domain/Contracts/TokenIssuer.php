<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Contracts;

use Src\Accounts\Sessions\Domain\ValueObjects\IssuedToken;
use Src\Shared\Domain\ValueObjects\UserId;

interface TokenIssuer
{
    /**
     * Issue a new access token for a device of the account, with the abilities of its role and an expiration, and
     * return it in plain text, the only time it is shown.
     *
     * @param  list<string>  $abilities
     */
    public function issue(UserId $userId, string $deviceName, array $abilities): IssuedToken;
}
