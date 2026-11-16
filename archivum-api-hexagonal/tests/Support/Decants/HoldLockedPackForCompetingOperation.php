<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;

/**
 * Makes two operations on the same pack compete in a known order, as when a provider's notice confirms its payment
 * while the queue expires it. The first one locks the pack, creates the file it was given so the other one starts, and
 * keeps its transaction open until another backend waits for it. The other one waits for that file before it starts,
 * and is given none. Without the lock of the pack the other one would never wait here, and the test fails.
 */
final class HoldLockedPackForCompetingOperation implements DecantPackRepository
{
    public bool $observedOverlap = false;

    private bool $held = false;

    public function __construct(private readonly DecantPackRepository $packs, private readonly ?string $lockedSignal) {}

    /** Wait until the first operation holds the lock of the pack, so the other one starts after it. */
    public static function waitUntilLocked(string $lockedSignal): void
    {
        $deadline = microtime(true) + 10;
        while (! file_exists($lockedSignal)) {
            if (microtime(true) >= $deadline) {
                throw new RuntimeException('The first operation never locked the pack.');
            }
            usleep(10000);
        }
    }

    public function nextId(): DecantPackId
    {
        return $this->packs->nextId();
    }

    public function add(DecantPack $pack): void
    {
        $this->packs->add($pack);
    }

    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack
    {
        $pack = $this->packs->find($id, $forUpdate);

        if ($forUpdate && $this->lockedSignal !== null && ! $this->held) {
            $this->held = true;
            if (! touch($this->lockedSignal)) {
                throw new RuntimeException('Could not tell the other operation that the pack is locked.');
            }
            $this->waitForTheCompetingOperation();
        }

        return $pack;
    }

    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack
    {
        return $this->packs->findByIdempotencyKey($customerId, $key);
    }

    public function save(DecantPack $pack): void
    {
        $this->packs->save($pack);
    }

    public function saveIfStatus(DecantPack $pack, DecantPackStatus $expected): bool
    {
        return $this->packs->saveIfStatus($pack, $expected);
    }

    public function addRedemption(DecantPackId $packId, string $orderLineId): void
    {
        $this->packs->addRedemption($packId, $orderLineId);
    }

    public function removeRedemption(DecantPackId $packId, string $orderLineId): bool
    {
        return $this->packs->removeRedemption($packId, $orderLineId);
    }

    /** With the pack locked, wait until another backend waits for this one. */
    private function waitForTheCompetingOperation(): void
    {
        $deadline = microtime(true) + 10;
        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);
            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing operation never waited for the locked pack.');
                }
                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }
}
