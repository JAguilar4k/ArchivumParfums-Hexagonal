<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final readonly class TwilioWhatsAppSender implements WhatsAppSender
{
    public const string SANDBOX_FROM = 'whatsapp:+14155238886';

    /**
     * @param  list<string>  $testNumbers
     * @param  array<string, string>|null  $trialContentSids  Null selects the classic Sandbox.
     */
    public function __construct(
        private string $sid,
        private string $token,
        private string $from,
        private array $testNumbers,
        private ?array $trialContentSids,
    ) {}

    public function supports(CustomerNotice $notice): bool
    {
        return $this->trialContentSids === null || isset($this->trialContentSids[$notice->value]);
    }

    public function send(CustomerNotice $notice, string $phone, string $message): void
    {
        if (! $this->supports($notice)) {
            Log::info('WhatsApp notice skipped: no Trial template for this notice.', ['notice_type' => $notice->value]);

            return;
        }

        if (preg_match('/^\+[1-9][0-9]{1,14}$/D', $phone) !== 1 || ! in_array($phone, $this->testNumbers, true)) {
            Log::info('WhatsApp notice skipped: recipient is outside the sandbox test list.');

            return;
        }

        $content = $this->trialContentSids === null
            ? ['Body' => $message]
            : ['ContentSid' => $this->trialContentSids[$notice->value]];

        try {
            $response = Http::asForm()
                ->withBasicAuth($this->sid, $this->token)
                ->withoutRedirecting()
                ->connectTimeout(2)
                ->timeout(5)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'From' => $this->from,
                    'To' => 'whatsapp:'.$phone,
                    ...$content,
                ]);
        } catch (ConnectionException) {
            Log::warning('Twilio could not be reached.');

            return;
        }

        if (! $response->successful()) {
            Log::warning('Twilio did not accept the WhatsApp notice.', ['status' => $response->status()]);
        }
    }
}
