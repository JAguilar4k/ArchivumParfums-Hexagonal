<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Security;

use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    public const string TOKEN_NAME = 'sesion';

    /** Create a Sanctum personal access token for the account; each sign-in gets its own. */
    public function issue(UserId $userId): string
    {
        return UserModel::query()
            ->findOrFail($userId->value)
            ->createToken(self::TOKEN_NAME)
            ->plainTextToken;
    }
}
