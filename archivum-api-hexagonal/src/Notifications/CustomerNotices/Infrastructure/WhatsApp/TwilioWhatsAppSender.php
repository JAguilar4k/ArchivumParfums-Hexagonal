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
     * Numbered variables of each approved template, in the order of its Spanish text in lang/es/notices.php.
     *
     * @var array<string, list<string>>
     */
    private const array TEMPLATE_VARIABLES = [
        'order_placed' => ['reference'],
        'appointment_booked' => ['starts_at', 'reference'],
    ];

    /**
     * @param  list<string>|null  $testNumbers  Null lets the business sender reach any customer.
     * @param  array<string, string>|null  $contentSids  Approved template per notice; null selects the Sandbox text.
     */
    public function __construct(
        private string $sid,
        private string $token,
        private string $from,
        private ?array $testNumbers,
        private ?array $contentSids,
    ) {}

    public function supports(CustomerNotice $notice): bool
    {
        return $this->contentSids === null || isset($this->contentSids[$notice->value]);
    }

    public function send(CustomerNotice $notice, string $phone, string $message, array $variables): void
    {
        if (! $this->supports($notice)) {
            Log::info('WhatsApp notice skipped: no approved template for this notice.', ['notice_type' => $notice->value]);

            return;
        }

        if (preg_match('/^\+[1-9][0-9]{1,14}$/D', $phone) !== 1) {
            Log::info('WhatsApp notice skipped: the customer phone is not in E.164.');

            return;
        }

        if ($this->testNumbers !== null && ! in_array($phone, $this->testNumbers, true)) {
            Log::info('WhatsApp notice skipped: recipient is outside the sandbox test list.');

            return;
        }

        $content = $this->contentSids === null
            ? ['Body' => $message]
            : ['ContentSid' => $this->contentSids[$notice->value], 'ContentVariables' => $this->contentVariables($notice, $variables)];

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

    /** @param array<string, string> $variables */
    private function contentVariables(CustomerNotice $notice, array $variables): string
    {
        $numbered = [];

        foreach (self::TEMPLATE_VARIABLES[$notice->value] as $index => $name) {
            $numbered[(string) ($index + 1)] = $variables[$name] ?? '';
        }

        return (string) json_encode($numbered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
