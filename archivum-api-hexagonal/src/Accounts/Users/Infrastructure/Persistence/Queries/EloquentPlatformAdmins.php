<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Queries;

use Src\Accounts\Users\Domain\Contracts\PlatformAdmins;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class EloquentPlatformAdmins implements PlatformAdmins
{
    /** Set the mark in the row of the account; users has no row policy, so no business needs to be pinned. */
    public function grant(UserId $userId): void
    {
        UserModel::query()->whereKey($userId->value)->update(['is_platform_admin' => true]);
    }

    /** Read the mark of the account. */
    public function includes(UserId $userId): bool
    {
        return UserModel::query()->whereKey($userId->value)->where('is_platform_admin', true)->exists();
    }
}
