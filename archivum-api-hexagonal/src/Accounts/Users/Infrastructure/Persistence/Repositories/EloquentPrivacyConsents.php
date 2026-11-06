<?php

declare(strict_types=1);

namespace Src\Accounts\Users\Infrastructure\Persistence\Repositories;

use Src\Accounts\Users\Domain\Contracts\PrivacyConsents;
use Src\Accounts\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Shared\Domain\ValueObjects\UserId;

final readonly class EloquentPrivacyConsents implements PrivacyConsents
{
    /** Keep the date and the version on the row of the account. */
    public function record(UserId $userId, string $noticeVersion): void
    {
        UserModel::query()->whereKey($userId->value)->update([
            'privacy_accepted_at' => now(),
            'privacy_notice_version' => $noticeVersion,
        ]);
    }
}
