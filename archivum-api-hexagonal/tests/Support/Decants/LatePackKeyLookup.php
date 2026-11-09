<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;

/** A repository whose first lookup by key arrives too early to see the pack another request is saving. */
final class LatePackKeyLookup implements DecantPackRepository
{
    private bool $firstLookup = true;

    /** Wrap the repository that holds the packs of the scenario. */
    public function __construct(private readonly InMemoryDecantPackRepository $inner) {}

    public function nextId(): DecantPackId
    {
        return $this->inner->nextId();
    }

    /** Delegate: the inner repository refuses a key already used. */
    public function add(DecantPack $pack): void
    {
        $this->inner->add($pack);
    }

    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack
    {
        return $this->inner->find($id, $forUpdate);
    }

    /** See nothing the first time, as if the other request had not committed yet; afterwards, delegate. */
    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack
    {
        if ($this->firstLookup) {
            $this->firstLookup = false;

            return null;
        }

        return $this->inner->findByIdempotencyKey($customerId, $key);
    }

    public function save(DecantPack $pack): void
    {
        $this->inner->save($pack);
    }
}
