<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\UserId;

interface TokenRevoker
{
    /** Delete one access token, so it no longer opens any session. */
    public function revoke(string $tokenId): void;

    /** Delete every access token of an account, for a change of role, a deactivation or a deleted account. */
    public function revokeAll(UserId $userId): void;
}
