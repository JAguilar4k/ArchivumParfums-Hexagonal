<?php

declare(strict_types=1);

namespace Src\Decants\Packs\Domain\Contracts;

use Src\Decants\Packs\Domain\Entities\DecantPack;
use Src\Decants\Packs\Domain\ValueObjects\DecantPackId;

interface DecantPackRepository
{
    /** Generate the identity of a new pack. */
    public function nextId(): DecantPackId;

    /** Persist a new pack in the business of the operation. */
    public function add(DecantPack $pack): void;

    /** Find a pack of the business of the operation; with $forUpdate, its row stays locked until the transaction ends. */
    public function find(DecantPackId $id, bool $forUpdate = false): ?DecantPack;

    /** Persist the status, the decants left and the expiry of a pack. */
    public function save(DecantPack $pack): void;
}
