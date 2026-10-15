<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;

final readonly class EndSession
{
    /** Receive the token revoking port. */
    public function __construct(private TokenRevoker $tokens) {}

    /** Close the session of one token; the other sessions of the account stay open. */
    public function execute(string $tokenId): void
    {
        $this->tokens->revoke($tokenId);
    }
}
