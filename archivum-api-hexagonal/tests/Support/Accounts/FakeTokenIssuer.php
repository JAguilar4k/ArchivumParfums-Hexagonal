<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Shared\Domain\ValueObjects\UserId;

final class FakeTokenIssuer implements TokenIssuer
{
    /** @var list<string> */
    public private(set) array $issuedFor = [];

    /** Record the account and answer a predictable token. */
    public function issue(UserId $userId): string
    {
        $this->issuedFor[] = $userId->value;

        return 'token-'.count($this->issuedFor);
    }
}
