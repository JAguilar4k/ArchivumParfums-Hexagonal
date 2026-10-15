<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Shared\Domain\ValueObjects\UserId;

final class FakeTokenRevoker implements TokenRevoker
{
    /** @var list<string> */
    public private(set) array $revoked = [];

    /** @var list<string> */
    public private(set) array $revokedAccounts = [];

    /** Record the revoked token. */
    public function revoke(string $tokenId): void
    {
        $this->revoked[] = $tokenId;
    }

    /** Record the account whose tokens were revoked. */
    public function revokeAll(UserId $userId): void
    {
        $this->revokedAccounts[] = $userId->value;
    }
}
