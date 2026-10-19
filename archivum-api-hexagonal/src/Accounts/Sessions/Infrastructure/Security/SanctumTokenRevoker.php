<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Security;

use Laravel\Sanctum\PersonalAccessToken;
use Src\Accounts\Sessions\Domain\Contracts\TokenRevoker;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class SanctumTokenRevoker implements TokenRevoker
{
    /** Delete the row of the token, not only mark it. */
    public function revoke(string $tokenId): void
    {
        PersonalAccessToken::query()->whereKey($tokenId)->delete();
    }

    /** Delete every token whose owner is the account. */
    public function revokeAll(UserId $userId): void
    {
        PersonalAccessToken::query()
            ->where('tokenable_type', new UserModel()->getMorphClass())
            ->where('tokenable_id', $userId->value)
            ->delete();
    }
}
