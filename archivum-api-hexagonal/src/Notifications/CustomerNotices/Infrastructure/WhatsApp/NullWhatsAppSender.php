<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\WhatsApp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Shared\Domain\Contracts\CustomerNotifier;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final readonly class NullWhatsAppSender implements CustomerNotifier, WhatsAppSender
{
    private const string SKIPPED = 'WhatsApp notice skipped: Twilio Sandbox is not configured.';

    /** With no sender, log after commit without writing a job into the queue. */
    public function schedule(CustomerNotice $notice, string $businessId, string $customerId, string $reference, array $details = []): void
    {
        DB::afterCommit(fn () => Log::info(self::SKIPPED));
    }

    /** The configuration may also have changed after a job was queued. */
    public function send(string $phone, string $message): void
    {
        Log::info(self::SKIPPED);
    }
}
