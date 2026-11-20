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
                : new TwilioWhatsAppSender($settings['sid'], $settings['token'], $settings['from'], $settings['testNumbers']);
        });
    }

    /** @return array{sid: string, token: string, from: string, testNumbers: list<string>}|null */
    private function sandboxSettings(): ?array
    {
        $sid = trim((string) config('archivum.whatsapp.sid'));
        $token = trim((string) config('archivum.whatsapp.token'));
        $from = trim((string) config('archivum.whatsapp.from'));
        $testNumbers = array_values(array_filter(array_map(trim(...), explode(',', (string) config('archivum.whatsapp.test_numbers')))));

        if (str_starts_with($from, '+')) {
            $from = 'whatsapp:'.$from;
        }

        if (preg_match('/^AC[a-f0-9]{32}$/iD', $sid) !== 1 || $token === '' || $testNumbers === [] || $from !== TwilioWhatsAppSender::SANDBOX_FROM) {
            return null;
        }

        return ['sid' => $sid, 'token' => $token, 'from' => $from, 'testNumbers' => $testNumbers];
    }
}
