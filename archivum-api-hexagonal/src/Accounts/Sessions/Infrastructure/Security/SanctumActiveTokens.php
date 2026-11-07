<?php

declare(strict_types=1);

namespace Src\Accounts\Sessions\Infrastructure\Security;

use Illuminate\Support\Facades\DB;
use Src\Accounts\Sessions\Domain\Contracts\ActiveTokens;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class SanctumActiveTokens implements ActiveTokens
{
    /**
     * Delete the Sanctum token with a single conditional DELETE: only a row of the account that has not expired goes,
     * so two simultaneous renewals of the same token delete it once. The instant comes from the application clock,
     * the same one Sanctum checks, so a simulated clock in the tests applies here too.
     */
    public function take(UserId $userId, string $tokenId): ?string
    {
        $row = DB::selectOne(
            'delete from personal_access_tokens
              where id = ? and tokenable_type = ? and tokenable_id = ?
                and (expires_at is null or expires_at > ?)
                and created_at > ?
          returning name',
            [
                $tokenId,
                new UserModel()->getMorphClass(),
                $userId->value,
                now(),
                now()->subMinutes((int) config('sanctum.expiration')),
            ],
        );

        return is_object($row) && isset($row->name) ? (string) $row->name : null;
    }
}
