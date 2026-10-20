<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Security;

use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    /** Create a Sanctum personal access token named after the device; each sign-in gets its own. */
    public function issue(UserId $userId, string $deviceName): string
    {
        return UserModel::query()
            ->findOrFail($userId->value)
            ->createToken($deviceName)
            ->plainTextToken;
    }
}
