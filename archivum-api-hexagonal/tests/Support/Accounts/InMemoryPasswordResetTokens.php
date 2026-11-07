<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use SensitiveParameter;
use Src\Accounts\Users\Domain\Contracts\PasswordResetNotifier;
use Src\Accounts\Users\Domain\Contracts\PasswordResetTokens;
use Src\Shared\Domain\ValueObjects\UserId;

final class InMemoryPasswordResetTokens implements PasswordResetNotifier, PasswordResetTokens
{
    /** @var array<string, string> */
    public private(set) array $tokens = [];

    /** @var list<array{string, string}> */
    public private(set) array $sent = [];

    public function __construct(private readonly bool $recentlyIssued = false) {}

    /** Create a predictable token, unless the test says one is recent. */
    public function issue(UserId $userId): ?string
    {
        return $this->recentlyIssued ? null : $this->tokens[$userId->value] = "token-{$userId->value}";
    }

    /** Compare with the token of the account. */
    public function isValid(UserId $userId, #[SensitiveParameter] string $token): bool
    {
        return ($this->tokens[$userId->value] ?? null) === $token;
    }

    /** Forget the token of the account. */
    public function revoke(UserId $userId): void
    {
        unset($this->tokens[$userId->value]);
    }

    /** Remember the link sent. */
    public function send(string $email, #[SensitiveParameter] string $token): void
    {
        $this->sent[] = [$email, $token];
    }
}
