<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\Queue;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Shared\Domain\Contracts\CustomerNotifier;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final readonly class NullCustomerNotifier implements CustomerNotifier
{
    public function schedule(CustomerNotice $notice, string $businessId, string $customerId, string $reference, array $details = []): void
    {
        DB::afterCommit(fn () => Log::info('WhatsApp notice skipped: Twilio is not configured.'));
    }
}
