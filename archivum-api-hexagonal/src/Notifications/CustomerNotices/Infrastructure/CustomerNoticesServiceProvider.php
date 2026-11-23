<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Notifications\CustomerNotices\Application\Contracts\NoticeRecipients;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Notifications\CustomerNotices\Infrastructure\Accounts\AccountsNoticeRecipients;
use Src\Notifications\CustomerNotices\Infrastructure\Queue\NullCustomerNotifier;
use Src\Notifications\CustomerNotices\Infrastructure\Queue\QueuedCustomerNotifier;
use Src\Notifications\CustomerNotices\Infrastructure\WhatsApp\NullWhatsAppSender;
use Src\Notifications\CustomerNotices\Infrastructure\WhatsApp\TwilioWhatsAppSender;
use Src\Shared\Domain\Contracts\CustomerNotifier;
use Src\Shared\Domain\ValueObjects\CustomerNotice;

final class CustomerNoticesServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        NoticeRecipients::class => AccountsNoticeRecipients::class,
    ];

    public function register(): void
    {
        $this->app->bind(CustomerNotifier::class, fn (): CustomerNotifier => $this->twilioSettings() === null
            ? new NullCustomerNotifier
            : new QueuedCustomerNotifier);

        $this->app->bind(WhatsAppSender::class, function (): WhatsAppSender {
            $settings = $this->twilioSettings();

            return $settings === null
                ? new NullWhatsAppSender
                : new TwilioWhatsAppSender($settings['sid'], $settings['token'], $settings['from'], $settings['testNumbers'], $settings['contentSids']);
        });
    }

    /**
     * The Sandbox sends the Spanish text to the numbers that joined it. A business sender of a paid account sends the
     * approved template of each notice to any customer, because a confirmation opens the conversation.
     *
     * @return array{sid: string, token: string, from: string, testNumbers: list<string>|null, contentSids: array<string, string>|null}|null
     */
    private function twilioSettings(): ?array
    {
        $sid = trim((string) config('archivum.whatsapp.sid'));
        $token = trim((string) config('archivum.whatsapp.token'));
        $from = trim((string) config('archivum.whatsapp.from'));
        $mode = trim((string) config('archivum.whatsapp.mode'));

        if (str_starts_with($from, '+')) {
            $from = 'whatsapp:'.$from;
        }

        if (preg_match('/^AC[a-f0-9]{32}$/iD', $sid) !== 1 || $token === '') {
            return null;
        }

        if ($mode === '' || $mode === 'sandbox') {
            $testNumbers = array_values(array_filter(array_map(trim(...), explode(',', (string) config('archivum.whatsapp.test_numbers')))));

            return $from === TwilioWhatsAppSender::SANDBOX_FROM && $testNumbers !== []
                ? ['sid' => $sid, 'token' => $token, 'from' => $from, 'testNumbers' => $testNumbers, 'contentSids' => null]
                : null;
        }

        if ($mode !== 'business' || preg_match('/^whatsapp:\+[1-9][0-9]{1,14}$/D', $from) !== 1 || $from === TwilioWhatsAppSender::SANDBOX_FROM) {
            return null;
        }

        $contentSids = array_filter([
            CustomerNotice::OrderPlaced->value => trim((string) config('archivum.whatsapp.order_content_sid')),
            CustomerNotice::AppointmentBooked->value => trim((string) config('archivum.whatsapp.appointment_content_sid')),
        ], fn (string $contentSid): bool => preg_match('/^HX[a-fA-F0-9]{32}$/D', $contentSid) === 1);

        return $contentSids === []
            ? null
            : ['sid' => $sid, 'token' => $token, 'from' => $from, 'testNumbers' => null, 'contentSids' => $contentSids];
    }
}
