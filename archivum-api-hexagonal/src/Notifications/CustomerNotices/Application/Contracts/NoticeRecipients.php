<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Application\Contracts;

interface NoticeRecipients
{
    /** Read the customer's phone within the transaction scoped to the business. */
    public function phoneOf(string $businessId, string $customerId): ?string;
}
