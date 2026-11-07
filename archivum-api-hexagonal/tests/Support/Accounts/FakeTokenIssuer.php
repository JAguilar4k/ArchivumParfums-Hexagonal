<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use DateTimeImmutable;
use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\ValueObjects\IssuedToken;
use Src\Shared\Domain\ValueObjects\UserId;

final class FakeTokenIssuer implements TokenIssuer
{
    /** Expiration of every token of the fake. */
    public const string EXPIRES_AT = '2026-11-18T13:00:00-06:00';

    /** @var list<array{string, string}> */
    public private(set) array $issued = [];

    /** @var list<list<string>> */
    public private(set) array $abilities = [];

    /** Remember the account, the device and the abilities and answer a predictable token with a fixed expiration. */
    public function issue(UserId $userId, string $deviceName, array $abilities): IssuedToken
    {
        $this->issued[] = [$userId->value, $deviceName];
        $this->abilities[] = $abilities;

        return new IssuedToken('token-'.count($this->issued), new DateTimeImmutable(self::EXPIRES_AT));
    }
}
