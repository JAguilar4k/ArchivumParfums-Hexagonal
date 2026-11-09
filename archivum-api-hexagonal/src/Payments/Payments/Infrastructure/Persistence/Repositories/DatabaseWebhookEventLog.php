<?php

declare(strict_types=1);

namespace Src\Payments\Payments\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Facades\DB;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

final readonly class DatabaseWebhookEventLog implements WebhookEventLog
{
    /**
     * webhook_events has no business_id and no row policy: a notice is recorded under the provider's identifier of
     * the event, (gateway, event_id). When the provider sends the same notice again, the row takes the new outcome,
     * unless it was already processed.
     */
    public function record(PaymentEvent $event, string $payload, ?string $error = null): void
    {
        $status = $error === null ? 'processed' : 'failed';

        DB::statement(<<<'SQL'
            INSERT INTO webhook_events (gateway, event_id, event_type, payload, status, error, processed_at, created_at, updated_at)
            VALUES (?, ?, ?, ?::jsonb, ?, ?, CASE WHEN ?::text = 'processed' THEN now() END, now(), now())
            ON CONFLICT (gateway, event_id) DO UPDATE
                SET status = excluded.status, error = excluded.error, processed_at = excluded.processed_at, updated_at = now()
                WHERE webhook_events.status <> 'processed'
            SQL, [$event->gateway, $event->eventId, $event->type, $payload, $status, $error, $status]);
    }
}
