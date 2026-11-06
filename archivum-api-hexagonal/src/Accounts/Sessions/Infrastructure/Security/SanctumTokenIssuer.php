<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Security;

use Src\Accounts\Sessions\Domain\Contracts\TokenIssuer;
use Src\Accounts\Sessions\Domain\ValueObjects\IssuedToken;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    /**
     * Create a Sanctum personal access token named after the device, with the abilities given; each sign-in gets its own.
     * It expires after the minutes of sanctum.expiration (SANCTUM_EXPIRATION).
     *
     * @param  list<string>  $abilities
     */
    public function issue(UserId $userId, string $deviceName, array $abilities): IssuedToken
    {
        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));

        $token = UserModel::query()
            ->findOrFail($userId->value)
            ->createToken($deviceName, $abilities, $expiresAt);

        return new IssuedToken($token->plainTextToken, $expiresAt->toDateTimeImmutable());
    }
}
