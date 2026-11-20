<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\WhatsApp;

use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;

final readonly class NullWhatsAppSender implements WhatsAppSender
{
    /** The configuration may have changed after the notice was queued. */
    public function send(string $phone, string $message): void
    {
        Log::info('WhatsApp notice skipped: Twilio Sandbox is not configured.');
    }
}
