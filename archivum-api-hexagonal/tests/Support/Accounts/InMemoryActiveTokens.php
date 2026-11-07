<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use Src\Accounts\Sessions\Domain\Contracts\ActiveTokens;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryActiveTokens implements ActiveTokens
{
    /** @var array<string, array{string, string}> account and device of each valid token, by its identifier */
    private array $tokens = [];

    /** Keep a valid token of an account for a device. */
    public function add(string $userId, string $tokenId, string $deviceName): void
    {
        $this->tokens[$tokenId] = [$userId, $deviceName];
    }

    /** Remove a valid token of the account and answer its device, once. */
    public function take(UserId $userId, string $tokenId): ?string
    {
        [$owner, $deviceName] = $this->tokens[$tokenId] ?? [null, null];

        if ($owner !== $userId->value) {
            return null;
        }

        unset($this->tokens[$tokenId]);

        return $deviceName;
    }
}
