<?php

declare(strict_types=1);

namespace Tests\Support\Sales;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Sales\Orders\Application\Contracts\DecantStock;

/** Keep the successful real reservation open until PostgreSQL sees the other request waiting on it. */
final class HoldReservationForCompetingRequest implements DecantStock
{
    public bool $observedOverlap = false;

    public function __construct(private readonly DecantStock $stock) {}

    public function reserve(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->stock->reserve($bottleId, $ml, $lineId, $userId);
        $deadline = microtime(true) + 10;

        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);

            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The other HTTP request never waited for the bottle reservation.');
                }

                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }

    public function release(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->stock->release($bottleId, $ml, $lineId, $userId);
    }

    public function consume(string $bottleId, int $ml, string $lineId, string $userId): void
    {
        $this->stock->consume($bottleId, $ml, $lineId, $userId);
    }
}
