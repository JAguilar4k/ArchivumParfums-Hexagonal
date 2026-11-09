<?php

declare(strict_types=1);

namespace Tests\Support\Decants;

use LogicException;
use Src\Decants\Packs\Domain\Contracts\DecantPackRepository;
use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;
use Src\Shared\Domain\Contracts\BusinessContext;

/**
 * Packs kept in memory, each one reachable only from its own business, like the adapter that filters by it. Like the
 * unique key of PostgreSQL, it refuses a second pack of the same customer with the same key.
 */
final class InMemoryDecantPackRepository implements DecantPackRepository
{
    /** @var array<string, DecantPack> */
    private array $packs = [];

    /** @var list<string> identifiers of the packs read to be changed, in the order they were read */
    public private(set) array $lockedIds = [];

    /** Packs inserted. */
    public private(set) int $added = 0;

    /** @var array<string, string> the pack each line of an order redeemed a decant of, by business and line */
    public private(set) array $redemptions = [];

    private int $sequence = 0;

    public function __construct(private readonly BusinessContext $context) {}

    /** Generate a predictable identity. */
    public function nextId(): DecantPackId
    {
        return new DecantPackId(sprintf('0192f0c4-0000-7000-9000-%012d', ++$this->sequence));
    }

    public function add(DecantPack $pack): void
    {
        foreach ($this->packs as $stored) {
            if ($pack->idempotencyKey !== null && $stored->businessId->equals($pack->businessId)
                && $stored->customerId === $pack->customerId && $stored->idempotencyKey?->value === $pack->idempotencyKey->value) {
                throw IdempotencyKeyReusedException::create();
            }
        }

        $this->packs[$pack->id->value] = $pack;
        $this->added++;
    }

    /** Find a pack of the business of the operation and record the ones read to be changed, which the adapter locks. */
    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack
    {
        if ($forUpdate) {
            $this->lockedIds[] = $id->value;
        }

        $pack = $this->packs[$id->value] ?? null;

        return $pack !== null && $pack->businessId->equals($this->context->current()) ? $pack : null;
    }

    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack
    {
        foreach ($this->packs as $pack) {
            if ($pack->businessId->equals($this->context->current()) && $pack->customerId === $customerId && $pack->idempotencyKey?->value === $key->value) {
                return $pack;
            }
        }

        return null;
    }

    public function save(DecantPack $pack): void
    {
        $this->packs[$pack->id->value] = $pack;
    }

    /** Like the unique key of PostgreSQL, a line of an order redeems one decant. */
    public function addRedemption(DecantPackId $packId, string $orderLineId): void
    {
        $line = $this->context->current()->value.':'.$orderLineId;
        if (isset($this->redemptions[$line])) {
            throw new LogicException('The line already redeemed a decant.');
        }

        $this->redemptions[$line] = $packId->value;
    }

    public function removeRedemption(DecantPackId $packId, string $orderLineId): bool
    {
        $line = $this->context->current()->value.':'.$orderLineId;
        if (($this->redemptions[$line] ?? null) !== $packId->value) {
            return false;
        }

        unset($this->redemptions[$line]);

        return true;
    }

    /**
     * The packs stored in every business.
     *
     * @return list<DecantPack>
     */
    public function all(): array
    {
        return array_values($this->packs);
    }
}
