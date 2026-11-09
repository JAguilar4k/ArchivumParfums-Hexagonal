<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Application\DTOs\RegisteredPaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

/** webhook_events has no business_id and no row policy: a notice is kept under the provider's identifier of the event. */
final readonly class DatabaseWebhookEventLog implements WebhookEventLog
{
    /**
     * The unique key (gateway, event_id) keeps only the first arrival: INSERT ... ON CONFLICT DO NOTHING inserts no
     * row for a repeated event. When two arrive at the same time, the later one waits for the earlier one to end and
     * then inserts nothing.
     */
    public function register(PaymentEvent $event, string $payload): ?string
    {
        $id = (string) Str::uuid7();
        $kept = DB::affectingStatement(<<<'SQL'
            INSERT INTO webhook_events (id, gateway, event_id, event_type, payload, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?::jsonb, 'pending', now(), now())
            ON CONFLICT (gateway, event_id) DO NOTHING
            SQL, [$id, $event->gateway, $event->eventId, $event->type, $payload]);

        return $kept === 1 ? $id : null;
    }

    public function lockForProcessing(string $id): ?RegisteredPaymentEvent
    {
        $event = DB::table('webhook_events')->where('id', $id)->where('status', '<>', 'processed')
            ->lockForUpdate()->first(['gateway', 'payload']);

        return $event === null ? null : new RegisteredPaymentEvent((string) $event->gateway, (string) $event->payload);
    }

    public function markProcessed(string $id): void
    {
        DB::update("UPDATE webhook_events SET status = 'processed', error = NULL, processed_at = now(), updated_at = now() WHERE id = ?", [$id]);
    }

    public function markFailed(string $id, string $error): void
    {
        DB::update("UPDATE webhook_events SET status = 'failed', error = ?, updated_at = now() WHERE id = ? AND status <> 'processed'", [$error, $id]);
    }
}
