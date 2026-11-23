<?php

declare(strict_types=1);

namespace Src\Notifications\CustomerNotices\Infrastructure\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\Notifications\CustomerNotices\Application\Contracts\NoticeRecipients;
use Src\Notifications\CustomerNotices\Domain\Contracts\WhatsAppSender;
use Src\Shared\Domain\ValueObjects\CustomerNotice;
use Src\Shared\Infrastructure\Queue\IdempotentJob;
use Src\Shared\Infrastructure\Queue\ScopeJobToBusiness;
use Src\Shared\Infrastructure\Tenancy\BusinessTransaction;

final class SendCustomerNoticeJob extends IdempotentJob
{
    /** @param array<string, string> $details */
    public function __construct(
        public readonly CustomerNotice $notice,
        public readonly string $businessId,
        public readonly string $customerId,
        public readonly string $reference,
        public readonly array $details,
    ) {}

    public function uniqueId(): string
    {
        return $this->notice->value.':'.$this->businessId.':'.$this->reference;
    }

    /** @return list<ScopeJobToBusiness> */
    public function middleware(): array
    {
        return [new ScopeJobToBusiness($this->businessId, ScopeJobToBusiness::CONTEXT)];
    }

    public function handle(BusinessTransaction $transaction, NoticeRecipients $recipients, WhatsAppSender $sender): void
    {
        if (! $sender->supports($this->notice)) {
            Log::info('WhatsApp notice skipped: this notice is not enabled.', ['notice_type' => $this->notice->value]);

            return;
        }

        $phone = $transaction->run($this->businessId, function () use ($recipients): ?string {
            $phone = $recipients->phoneOf($this->businessId, $this->customerId);

            if ($phone === null || $phone === '') {
                Log::info('WhatsApp notice skipped: no customer phone.');

                return null;
            }

            // A timeout can mean Twilio already received the notice. Claim the attempt before sending.
            $claimed = DB::table('customer_notice_attempts')->insertOrIgnore([
                'business_id' => $this->businessId,
                'notice_type' => $this->notice->value,
                'reference_id' => $this->reference,
            ]);

            return $claimed === 1 ? $phone : null;
        });

        if ($phone === null) {
            return;
        }

        $variables = $this->details + ['reference' => $this->reference];

        $sender->send($this->notice, $phone, (string) __('notices.'.$this->notice->value, $variables, 'es'), $variables);
    }
}
