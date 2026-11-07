<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Domain\Contracts;

use Src\Shared\Domain\ValueObjects\UserId;

interface ActiveTokens
{
    /**
     * Delete a token of the account only while it is still valid, in a single conditional statement, and answer the
     * device it was issued for. Null means it had already expired or been revoked, also by a simultaneous renewal.
     */
    public function take(UserId $userId, string $tokenId): ?string;
}
