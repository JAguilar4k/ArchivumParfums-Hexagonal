<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\Accounts;

use Src\Accounts\Users\Infrastructure\Persistence\Models\CustomerModel;
use Src\Notifications\CustomerNotices\Application\Contracts\NoticeRecipients;

final readonly class AccountsNoticeRecipients implements NoticeRecipients
{
    public function phoneOf(string $businessId, string $customerId): ?string
    {
        return CustomerModel::query()->where('business_id', $businessId)
            ->whereKey($customerId)
            ->first(['phone'])?->phone;
    }
}
