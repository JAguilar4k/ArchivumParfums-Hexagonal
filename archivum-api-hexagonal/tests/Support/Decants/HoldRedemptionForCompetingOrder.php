<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Decants\Packs\Domain\Contracts\DecantPackBalance;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;

/**
 * Keep the real conditional UPDATE, but hold the transaction that took a decant until another connection waits for its
 * row: the competing order has then read the pack with its decant still there and waits on the same statement.
 */
final class HoldRedemptionForCompetingOrder implements DecantPackBalance
{
    public bool $observedOverlap = false;

    public function __construct(private readonly DecantPackBalance $balance) {}

    public function take(DecantPackId $id, DateTimeImmutable $at): bool
    {
        if (! $this->balance->take($id, $at)) {
            return false;
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
                    throw new RuntimeException('The competing order never waited for the decant this one took.');
                }

                usleep(10000);
            }
        } while (! $this->observedOverlap);

        return true;
    }
}
