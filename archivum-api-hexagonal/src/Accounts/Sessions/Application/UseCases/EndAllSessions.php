<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class EndAllSessions
{
    /** Receive the token revoking port. */
    public function __construct(private TokenRevoker $tokens) {}

    /** Close every session of an account. */
    public function execute(string $userId): void
    {
        $this->tokens->revokeAll(new UserId($userId));
    }
}
