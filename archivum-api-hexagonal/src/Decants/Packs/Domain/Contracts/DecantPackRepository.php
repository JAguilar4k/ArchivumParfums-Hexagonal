<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Contracts;

use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\Exceptions\IdempotencyKeyReusedException;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackStatus;
use Src\Decants\Packs\Domain\ValueObjects\IdempotencyKey;

interface DecantPackRepository
{
    /** Generate the identity of a new pack. */
    public function nextId(): DecantPackId;

    /**
     * Persist a new pack in the business of the operation.
     *
     * @throws IdempotencyKeyReusedException when the customer already bought a pack with the same key
     */
    public function add(DecantPack $pack): void;

    /** Find a pack of the business of the operation; with $forUpdate, its row stays locked until the transaction ends. */
    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack;

    /** Find the pack a customer of the business of the operation bought with a key, if the key was used. */
    public function findByIdempotencyKey(string $customerId, IdempotencyKey $key): ?DecantPack;

    /** Persist the status, the decants left and the expiry of a pack. */
    public function save(DecantPack $pack): void;

    /** Persist the pack like save() only while its row still has the status it was read with; answer whether it did. */
    public function saveIfStatus(DecantPack $pack, DecantPackStatus $expected): bool;

    /** Keep that a decant of the pack was redeemed in a line of an order of the business of the operation (R34). */
    public function addRedemption(DecantPackId $packId, string $orderLineId): void;

    /**
     * Forget the redemption of a line of an order whose decant goes back to the pack. Answer whether the line had
     * redeemed a decant of that pack, so a decant is given back only once.
     */
    public function removeRedemption(DecantPackId $packId, string $orderLineId): bool;
}
