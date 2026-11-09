<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;

/**
 * Keep the real PostgreSQL repository, but hold the transaction that inserted a pack until another connection waits
 * for it: the purchase sent again at the same instant has then inserted the same key too and waits on the unique index.
 */
final class HoldPackForCompetingPurchase implements DecantPackRepository
{
    public bool $observedOverlap = false;

    public function __construct(private readonly DecantPackRepository $repository) {}

    public function nextId(): DecantPackId
    {
        return $this->repository->nextId();
    }

    public function add(DecantPack $pack): void
    {
        $this->repository->add($pack);
        $deadline = microtime(true) + 10;

        do {
            DB::statement('select pg_stat_clear_snapshot()');
            $this->observedOverlap = (bool) DB::scalar(<<<'SQL'
                select exists (select 1 from pg_stat_activity
                    where datname = current_database() and pg_backend_pid() = any (pg_blocking_pids(pid)))
                SQL);

            if (! $this->observedOverlap) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('The competing purchase never waited for the pack of this one.');
                }

                usleep(10000);
            }
        } while (! $this->observedOverlap);
    }

    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack
    {
        return $this->repository->find($id, $forUpdate);
    }

    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack
    {
        return $this->repository->findByIdempotencyKey($customerId, $key);
    }

    public function save(DecantPack $pack): void
    {
        $this->repository->save($pack);
    }

    public function addRedemption(DecantPackId $packId, string $orderLineId): void
    {
        $this->repository->addRedemption($packId, $orderLineId);
    }

    public function removeRedemption(DecantPackId $packId, string $orderLineId): bool
    {
        return $this->repository->removeRedemption($packId, $orderLineId);
    }
}
