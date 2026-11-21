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
        $this->app->bind(CustomerNotifier::class, fn (): CustomerNotifier => $this->sandboxSettings() === null
            ? new NullCustomerNotifier
            : new QueuedCustomerNotifier);

        $this->app->bind(WhatsAppSender::class, function (): WhatsAppSender {
            $settings = $this->sandboxSettings();

            return $settings === null
                ? new NullWhatsAppSender
                : new TwilioWhatsAppSender($settings['sid'], $settings['token'], $settings['from'], $settings['testNumbers'], $settings['trialContentSids']);
        });
    }

    /** @return array{sid: string, token: string, from: string, testNumbers: list<string>, trialContentSids: array<string, string>|null}|null */
    private function sandboxSettings(): ?array
    {
        $sid = trim((string) config('archivum.whatsapp.sid'));
        $token = trim((string) config('archivum.whatsapp.token'));
        $from = trim((string) config('archivum.whatsapp.from'));
        $mode = trim((string) config('archivum.whatsapp.mode'));
        $testNumbers = array_values(array_filter(array_map(trim(...), explode(',', (string) config('archivum.whatsapp.test_numbers')))));

        if (str_starts_with($from, '+')) {
            $from = 'whatsapp:'.$from;
        }

        $validFrom = match ($mode) {
            '', 'sandbox' => $from === TwilioWhatsAppSender::SANDBOX_FROM,
            'trial' => preg_match('/^whatsapp:\+[1-9][0-9]{1,14}$/D', $from) === 1,
            default => false,
        };

        if (preg_match('/^AC[a-f0-9]{32}$/iD', $sid) !== 1 || $token === '' || $testNumbers === [] || ! $validFrom) {
            return null;
        }

        $trialContentSids = null;

        if ($mode === 'trial') {
            $trialContentSids = array_filter([
                CustomerNotice::OrderPlaced->value => trim((string) config('archivum.whatsapp.order_content_sid')),
                CustomerNotice::AppointmentBooked->value => trim((string) config('archivum.whatsapp.appointment_content_sid')),
            ], fn (string $contentSid): bool => preg_match('/^HX[a-fA-F0-9]{32}$/D', $contentSid) === 1);

            if ($trialContentSids === []) {
                return null;
            }
        }

        return ['sid' => $sid, 'token' => $token, 'from' => $from, 'testNumbers' => $testNumbers, 'trialContentSids' => $trialContentSids];
    }
}
