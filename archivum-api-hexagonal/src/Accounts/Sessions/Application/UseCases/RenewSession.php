<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Application\UseCases;

use Src\Accounts\Sessions\Application\Contracts\StaffMemberships;
use Src\Accounts\Sessions\Application\DTOs\StartedSession;
use Src\Accounts\Sessions\Domain\Contracts\ActiveTokens;
use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\Exceptions\SessionNotRenewableException;
use Src\Accounts\Sessions\Domain\ValueObjects\Ability;
use Src\Accounts\Sessions\Domain\ValueObjects\AccountRole;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class RenewSession
{
    /** Receive the active tokens, the token, the staff memberships and the transaction ports. */
    public function __construct(
        private ActiveTokens $activeTokens,
        private TokenIssuer $tokens,
        private StaffMemberships $memberships,
        private TransactionRunner $transactions,
    ) {}

    /**
     * Swap a token that is still valid for a new one on the same device, with the abilities of the current role.
     * Deleting the old token and issuing the new one share a transaction, so of two simultaneous renewals only the
     * one that deleted the row gets a token.
     *
     * @throws SessionNotRenewableException
     */
    public function execute(string $userId, string $tokenId): StartedSession
    {
        $id = new UserId($userId);

        return $this->transactions->run(function () use ($id, $tokenId): StartedSession {
            $deviceName = $this->activeTokens->take($id, $tokenId) ?? throw SessionNotRenewableException::create();
            $role = $this->memberships->of($id)->role ?? AccountRole::Customer;
            $token = $this->tokens->issue($id, $deviceName, Ability::namesGrantedTo($role));

            return new StartedSession($token->plainTextToken, $token->expiresAt->format(DATE_ATOM));
        });
    }
}
