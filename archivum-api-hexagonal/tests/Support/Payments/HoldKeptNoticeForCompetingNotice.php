<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Payments\Payments\Application\Contracts\WebhookEventLog;
use Src\Payments\Payments\Application\DTOs\RegisteredPaymentEvent;
use Src\Payments\Payments\Domain\ValueObjects\PaymentEvent;

/**
 * Keeps open the transaction that kept a notice until another backend waits for it, as when the provider sends the same
 * notice twice at the same time: the second arrival then reaches the unique key while the first one is not committed.
 */
final class HoldKeptNoticeForCompetingNotice implements WebhookEventLog
{
    public bool $observedOverlap = false;

    public function __construct(private readonly WebhookEventLog $log) {}

    public function register(PaymentEvent $event, string $payload): ?string
    {
        $id = $this->log->register($event, $payload);
        if ($id === null) {
            return null;
        }

        $deadline = microtime(true) + 10;
        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);
            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing notice never waited for the kept one.');
                }
                usleep(10000);
            }
        } while (! $this->observedOverlap);

        return $id;
    }

    public function lockForProcessing(string $id): ?RegisteredPaymentEvent
    {
        return $this->log->lockForProcessing($id);
    }

    public function markProcessed(string $id): void
    {
        $this->log->markProcessed($id);
    }

    public function markFailed(string $id, string $error): void
    {
        $this->log->markFailed($id, $error);
    }
}
