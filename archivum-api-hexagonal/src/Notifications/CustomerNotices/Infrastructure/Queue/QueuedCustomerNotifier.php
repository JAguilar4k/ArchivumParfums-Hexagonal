<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\Queue;

use Src\Notifications\CustomerNotices\Infrastructure\Jobs\SendCustomerNoticeJob;
use Src\Shared\Domain\Contracts\CustomerNotifier;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final readonly class QueuedCustomerNotifier implements CustomerNotifier
{
    public function schedule(CustomerNotice $notice, string $businessId, string $customerId, string $reference, array $details = []): void
    {
        SendCustomerNoticeJob::dispatch($notice, $businessId, $customerId, $reference, $details)->afterCommit();
    }
}
