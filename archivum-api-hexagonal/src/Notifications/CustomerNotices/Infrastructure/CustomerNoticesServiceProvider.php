<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Notifications\CustomerNotices\Infrastructure\Queue\QueuedCustomerNotifier;
use Src\Notifications\CustomerNotices\Infrastructure\WhatsApp\NullWhatsAppSender;
use Src\Notifications\CustomerNotices\Infrastructure\WhatsApp\TwilioWhatsAppSender;
use Src\Shared\Domain\Contracts\CustomerNotifier;

final class CustomerNoticesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerNotifier::class, fn (): CustomerNotifier => $this->notifierFor($this->app->make(WhatsAppSender::class)));
        $this->app->bind(WhatsAppSender::class, function (): WhatsAppSender {
            $sid = trim((string) config('archivum.whatsapp.sid'));
            $token = trim((string) config('archivum.whatsapp.token'));
            $from = trim((string) config('archivum.whatsapp.from'));
            $testNumbers = array_values(array_filter(array_map(trim(...), explode(',', (string) config('archivum.whatsapp.test_numbers')))));

            if (str_starts_with($from, '+')) {
                $from = 'whatsapp:'.$from;
            }

            if (preg_match('/^AC[a-f0-9]{32}$/iD', $sid) !== 1 || $token === '' || $testNumbers === [] || $from !== TwilioWhatsAppSender::SANDBOX_FROM) {
                return new NullWhatsAppSender;
            }

            return new TwilioWhatsAppSender($sid, $token, $from, $testNumbers);
        });
    }

    private function notifierFor(WhatsAppSender $sender): CustomerNotifier
    {
        return $sender instanceof NullWhatsAppSender ? $sender : new QueuedCustomerNotifier;
    }
}
