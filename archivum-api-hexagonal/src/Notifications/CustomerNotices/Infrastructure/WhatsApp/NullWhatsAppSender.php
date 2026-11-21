<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\WhatsApp;

use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final readonly class NullWhatsAppSender implements WhatsAppSender
{
    public function supports(CustomerNotice $notice): bool
    {
        return false;
    }

    /** The configuration may have changed after the notice was queued. */
    public function send(CustomerNotice $notice, string $phone, string $message): void
    {
        Log::info('WhatsApp notice skipped: Twilio Sandbox is not configured.');
    }
}
