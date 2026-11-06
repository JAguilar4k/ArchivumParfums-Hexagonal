<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Shared\Domain\ValueObjects\UserId;

final class FakeTokenIssuer implements TokenIssuer
{
    /** @var list<array{string, string}> */
    public private(set) array $issued = [];

    /** @var list<list<string>> */
    public private(set) array $abilities = [];

    /** Remember the account, the device and the abilities and answer a predictable token. */
    public function issue(UserId $userId, string $deviceName, array $abilities): string
    {
        $this->issued[] = [$userId->value, $deviceName];
        $this->abilities[] = $abilities;

        return 'token-'.count($this->issued);
    }
}
